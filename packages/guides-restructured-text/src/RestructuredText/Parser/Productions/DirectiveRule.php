<?php

declare(strict_types=1);

/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link https://phpdoc.org
 */

namespace phpDocumentor\Guides\RestructuredText\Parser\Productions;

use InvalidArgumentException;
use phpDocumentor\Guides\Nodes\CompoundNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Directives\BaseDirective as DirectiveHandler;
use phpDocumentor\Guides\RestructuredText\Parser\Buffer;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use phpDocumentor\Guides\RestructuredText\Parser\DirectiveOption;
use phpDocumentor\Guides\RestructuredText\Parser\DocumentParserContext;
use phpDocumentor\Guides\RestructuredText\Parser\LinesIterator;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_merge;
use function explode;
use function is_string;
use function ltrim;
use function mb_strlen;
use function min;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;

use const PHP_INT_MAX;

/**
 * @link https://docutils.sourceforge.io/docs/ref/rst/restructuredtext.html#directives
 *
 * @implements Rule<Node>
 */
final class DirectiveRule implements Rule
{
    public const PRIORITY = 70;

    /** @var array<string, DirectiveHandler> */
    private array $directives;

    /** @param iterable<DirectiveHandler> $directives */
    public function __construct(
        private readonly InlineMarkupRule $inlineMarkupRule,
        private readonly LoggerInterface $logger,
        iterable $directives = [],
    ) {
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }
    }

    private function registerDirective(DirectiveHandler $directive): void
    {
        $this->directives[strtolower($directive->getName())] = $directive;
        foreach ($directive->getAliases() as $alias) {
            $this->directives[strtolower($alias)] = $directive;
        }
    }

    public function applies(DocumentParserContext $documentParser): bool
    {
        return $this->isDirective($documentParser->getDocumentIterator()->current());
    }

    private function isDirective(string $line): bool
    {
        return preg_match('/^\.\.\s+(\|(.+)\| |)([^\s]+)::( (.*)|)$/mUsi', $line) > 0;
    }

    public function apply(DocumentParserContext $documentParserContext, CompoundNode|null $on = null): Node|null
    {
        $documentIterator = $documentParserContext->getDocumentIterator();
        $openingLine = $documentIterator->current();
        $directive = $this->parseDirective($openingLine);

        if ($directive === null) {
            return null;
        }

        $this->parseDirectiveContent($directive, $documentParserContext);

        $directiveHandler = $this->getDirectiveHandler($directive);
        if ($directiveHandler === null) {
            $message = sprintf(
                'Unknown directive: "%s" %sfor line "%s"',
                $directive->getName(),
                $documentParserContext->getContext()->getCurrentFileName() !== '' ? sprintf(
                    'in "%s" ',
                    $documentParserContext->getContext()->getCurrentFileName(),
                ) : '',
                $openingLine,
            );

            $this->logger->error($message, $documentParserContext->getContext()->getLoggerInformation());

            return null;
        }

        $this->interpretDirectiveOptions($documentIterator, $directive);
        $buffer = $this->collectDirectiveContents($documentIterator);

        // Processing the Directive, the handler is responsible for adding the right Nodes to the document.
        try {
            $node = $directiveHandler->process(
                $documentParserContext->withContentsPreserveSpace($buffer->getLinesString()),
                $directive,
            );

            if ($node === null) {
                return null;
            }

            $node = $this->postProcessNode($node, $directive->getOptions());

            if ($directive->getVariable() !== '') {
                $documentParserContext->getDocument()->addVariable($directive->getVariable(), $node);

                return null;
            }

            return $node;
        } catch (Throwable $e) {
            $message = sprintf(
                'Error while processing "%s" directive%s: %s',
                $directiveHandler->getName(),
                $documentParserContext->getContext()->getCurrentFileName() !== '' ? sprintf(
                    ' in "%s"',
                    $documentParserContext->getContext()->getCurrentFileName(),
                ) : '',
                $e->getMessage(),
            );


            $this->logger->error($message, $documentParserContext->getContext()->getLoggerInformation());
        }

        return null;
    }

    private function parseDirectiveContent(Directive $directive, DocumentParserContext $documentParserContext): void
    {
        if ($directive->getData() === '') {
            return;
        }

        $inlineNode = $this->inlineMarkupRule->apply(
            $documentParserContext->withContents($directive->getData()),
            null,
        );
        $directive->setDataNode($inlineNode);
    }

    /**
     * Post processes a node created by a directive to apply common options
     *
     * @param DirectiveOption[] $options
     */
    private function postProcessNode(Node $node, array $options): Node
    {
        foreach ($options as $option) {
            if ($option->getName() !== 'class' || !is_string($option->getValue()) || $option->getValue() === '') {
                continue;
            }

            $node->setClasses(array_merge($node->getClasses(), explode(' ', (string) $option->getValue())));
        }

        return $node;
    }

    private function parseDirective(string $line): Directive|null
    {
        if (preg_match('/^\.\.\s+(\|(.+)\| |)([^\s]+)::( (.*)|)$/mUsi', $line, $match) > 0) {
            return new Directive(
                $match[2],
                $match[3],
                trim($match[4]),
            );
        }

        return null;
    }

    private function getDirectiveHandler(Directive $directive): DirectiveHandler|null
    {
        return $this->directives[strtolower($directive->getName())] ?? null;
    }

    private function interpretDirectiveOptions(LinesIterator $documentIterator, Directive $directive): void
    {
        while (
            $documentIterator->getNextLine() !== null && $this->isDirectiveOption($documentIterator->getNextLine())
        ) {
            $documentIterator->next();
            $directiveOption = $this->parseDirectiveOption($documentIterator->current());
            $this->collectDirectiveOptionContent($documentIterator, $directiveOption);
            $directive->addOption($directiveOption);
        }

        if (!$this->isDirectiveOption($documentIterator->current())) {
            return;
        }

        $documentIterator->next();
    }

    /**
     * Collects the content of multiline directive options:
     *
     * .. figure:: foo.jpg
     *     :width: 100
     *     :alt: Field options might use
     *       more than one line
     *
     *     This is a foo!
     */
    private function collectDirectiveOptionContent(
        LinesIterator $documentIterator,
        DirectiveOption $directiveOption,
    ): void {
        while (
            !LinesIterator::isNullOrEmptyLine($documentIterator->getNextLine())
            && !$this->isDirectiveOption($documentIterator->getNextLine())
        ) {
            $documentIterator->next();
            $directiveOption->appendValue(' ' . trim($documentIterator->current()));
        }
    }

    private function isDirectiveOption(string|null $line): bool
    {
        if ($line === null) {
            return false;
        }

        try {
            $this->parseDirectiveOption($line);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Directive options are stored in the field-list syntax:
     *
     * .. figure:: foo.jpg
     *     :width: 100
     *     :alt: Field options might use
     *       more than one line
     *     :yet another option: abc
     *     :empty option:
     *
     * @throws InvalidArgumentException
     */
    private function parseDirectiveOption(string $line): DirectiveOption
    {
        if (preg_match('/^(\s+):(.+): (.*)$/mUsi', $line, $match) > 0) {
            return new DirectiveOption($match[2], trim($match[3]));
        }

        if (preg_match('/^(\s+):(.+):(\s*)$/mUsi', $line, $match) > 0) {
            return new DirectiveOption($match[2], true);
        }

        throw new InvalidArgumentException('Not a valid directive option');
    }

    private function collectDirectiveContents(LinesIterator $documentIterator): Buffer
    {
        $buffer = new Buffer();
        $minIndenting = PHP_INT_MAX;
        while (LinesIterator::isBlockLine($documentIterator->getNextLine())) {
            $documentIterator->next();
            $line = $documentIterator->current();
            if (LinesIterator::isEmptyLine($line) === false) {
                $indenting = mb_strlen($line) - mb_strlen(ltrim($line));
                $minIndenting = min($minIndenting, $indenting);
            }

            $buffer->push($line);
        }

        $buffer->unIndent($minIndenting);

        return $buffer;
    }
}
