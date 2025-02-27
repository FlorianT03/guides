<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\RestructuredText\Directives;

use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use phpDocumentor\Guides\RestructuredText\Parser\DocumentParserContext;

/**
 * Add a meta title to the document
 *
 * .. title:: Page title
 */
class TitleDirective extends BaseDirective
{
    public function getName(): string
    {
        return 'title';
    }

    /** {@inheritDoc} */
    public function process(
        DocumentParserContext $documentParserContext,
        Directive $directive,
    ): Node|null {
        $document = $documentParserContext->getDocument();
        $document->setMetaTitle($directive->getData());

        return null;
    }
}
