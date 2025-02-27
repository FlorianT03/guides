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

namespace phpDocumentor\Guides\NodeRenderers;

use phpDocumentor\Guides\Nodes\Node;

class InMemoryNodeRendererFactory implements NodeRendererFactory
{
    /**
     * @param iterable<NodeRenderer<Node>> $nodeRenderers
     * @param NodeRenderer<Node> $defaultNodeRenderer
     */
    public function __construct(private readonly iterable $nodeRenderers, private readonly NodeRenderer $defaultNodeRenderer)
    {
        foreach ($nodeRenderers as $nodeRenderer) {
            if (!$nodeRenderer instanceof NodeRendererFactoryAware) {
                continue;
            }

            $nodeRenderer->setNodeRendererFactory($this);
        }

        if (!$defaultNodeRenderer instanceof NodeRendererFactoryAware) {
            return;
        }

        $defaultNodeRenderer->setNodeRendererFactory($this);
    }

    public function get(Node $node): NodeRenderer
    {
        foreach ($this->nodeRenderers as $nodeRenderer) {
            if ($nodeRenderer->supports($node)) {
                return $nodeRenderer;
            }
        }

        return $this->defaultNodeRenderer;
    }
}
