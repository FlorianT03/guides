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

namespace phpDocumentor\Guides\RestructuredText\Directives;

use phpDocumentor\Guides\Nodes\DocumentNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Nodes\TopicNode;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;

class TopicDirective extends SubDirective
{
    /** {@inheritDoc} */
    final protected function processSub(
        DocumentNode $document,
        Directive $directive,
    ): Node|null {
        return new TopicNode(
            $directive->getData(),
            $document->getChildren(),
        );
    }

    public function getName(): string
    {
        return 'topic';
    }
}
