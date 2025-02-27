<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\RestructuredText\Parser\Productions;

use phpDocumentor\Guides\Nodes\DefinitionListNode;
use phpDocumentor\Guides\Nodes\DefinitionLists\DefinitionListItemNode;
use phpDocumentor\Guides\Nodes\DefinitionLists\DefinitionNode;
use phpDocumentor\Guides\Nodes\InlineCompoundNode;
use phpDocumentor\Guides\Nodes\RawNode;
use PHPUnit\Framework\Attributes\DataProvider;

final class DefinitionListRuleTest extends RuleTestCase
{
    private DefinitionListRule $rule;

    protected function setUp(): void
    {
        $this->rule = new DefinitionListRule($this->givenInlineMarkupRule(), $this->givenCollectAllRuleContainer());
    }

    #[DataProvider('definitionListProvider')]
    public function testAppliesReturnsTrueOnValidInput(string $input): void
    {
        $context = $this->createContext($input);
        self::assertTrue($this->rule->applies($context));
    }

    #[DataProvider('isDefinitionListFalseProvider')]
    public function testAppliesReturnsFalseOnInvalidInput(string $input): void
    {
        $context = $this->createContext($input);
        self::assertFalse($this->rule->applies($context));
    }

    public function testParseDefinitionList(): void
    {
        $input = <<<'RST'
term 1
    Definition 1.

term 2
    Definition 2, paragraph 1.
    .. note::


        This is a note belongs to definition 2.

    Definition 2, paragraph 2.

term 3 : classifier
    Definition 3.

term 4 : classifier one : classifier two
    Definition 4.

\- term 5
    Without escaping, this would be an option list item.

... another definition:
    With two dots this would be a directive.

This is normal text again.
RST;

        $context = $this->createContext($input);


        $result = $this->rule->apply($context);
        $expected = new DefinitionListNode(
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('term 1'),
                [],
                [
                    new DefinitionNode(
                        [
                            new RawNode('Definition 1.'),
                        ],
                    ),
                ],
            ),
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('term 2'),
                [],
                [
                    new DefinitionNode(
                        [
                            new RawNode(<<<'RST'
Definition 2, paragraph 1.
.. note::


    This is a note belongs to definition 2.

Definition 2, paragraph 2.
RST),
                        ],
                    ),
                ],
            ),
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('term 3'),
                [InlineCompoundNode::getPlainTextInlineNode('classifier')],
                [
                    new DefinitionNode(
                        [
                            new RawNode('Definition 3.'),
                        ],
                    ),
                ],
            ),
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('term 4'),
                [
                    InlineCompoundNode::getPlainTextInlineNode('classifier one'),
                    InlineCompoundNode::getPlainTextInlineNode('classifier two'),
                ],
                [
                    new DefinitionNode(
                        [
                            new RawNode('Definition 4.'),
                        ],
                    ),
                ],
            ),
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('- term 5'),
                [],
                [
                    new DefinitionNode(
                        [
                            new RawNode('Without escaping, this would be an option list item.'),
                        ],
                    ),
                ],
            ),
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('... another definition:'),
                [],
                [
                    new DefinitionNode(
                        [
                            new RawNode('With two dots this would be a directive.'),
                        ],
                    ),
                ],
            ),
        );

        self::assertEquals($expected, $result);
        self::assertRemainingEquals('This is normal text again.' . "\n", $context->getDocumentIterator());
    }

    public function testDefinitionListFollowedByDirective(): void
    {
        $input = <<<'RST'
term 1
    Definition 1.
    
.. some:: directive
    :argument: whatever 
RST;

        $context = $this->createContext($input);


        $result = $this->rule->apply($context);
        $expected = new DefinitionListNode(
            new DefinitionListItemNode(
                InlineCompoundNode::getPlainTextInlineNode('term 1'),
                [],
                [
                    new DefinitionNode(
                        [
                            new RawNode('Definition 1.'),
                        ],
                    ),
                ],
            ),
        );

        self::assertEquals($expected, $result);
        self::assertRemainingEquals(<<<'RST'
.. some:: directive
    :argument: whatever

RST, $context->getDocumentIterator());
    }

    /** @return array<string, string[]> */
    public static function definitionListProvider(): array
    {
        return [
            'line ending with colon and space' => ["Test:\n  Definition"],
            'line ending with newline' => ["Test\n  Definition"],
            'line ending with two spaces' => ["Test  \n  Definition"],
            'term with classifiers' => [
                <<<'EOT'
Term 1: classifier 1: classifier 2
  Definition
EOT,
            ],
            'multiple definitions' => [
                <<<'EOT'
Term 2: classifier 1
  Definition 1
  Definition 2
EOT,
            ],
        ];
    }

    /** @return array<string, string[]> */
    public static function isDefinitionListFalseProvider(): array
    {
        return [
            'empty lines' => [''],
            'line ending with newline' => ["Test\n\n  Definition"],
            'Next line is not a block line' => ["Test\nDefinition"],
        ];
    }
}
