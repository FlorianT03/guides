<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\Compiler;

use phpDocumentor\Guides\Compiler\NodeTransformers\NodeTransformerFactory;
use phpDocumentor\Guides\Compiler\ShadowTree\TreeNode;
use phpDocumentor\Guides\Nodes\DocumentNode;
use phpDocumentor\Guides\Nodes\Node;

final class DocumentNodeTraverser
{
    public function __construct(
        private readonly NodeTransformerFactory $nodeTransformerFactory,
        private readonly int $priority,
    ) {
    }

    public function traverse(DocumentNode $node, CompilerContext $compilerContext): Node|null
    {
        foreach ($this->nodeTransformerFactory->getTransformers() as $transformer) {
            if ($transformer->getPriority() !== $this->priority) {
                continue;
            }

            $this->traverseForTransformer($transformer, $compilerContext->getShadowTree(), $compilerContext);
        }

        return $compilerContext->getShadowTree()->getNode();
    }

    /**
     * @param NodeTransformer<Node> $transformer
     * @param TreeNode<Node>|TreeNode<DocumentNode> $shadowNode
     *
     * return TNode|null
     */
    private function traverseForTransformer(
        NodeTransformer $transformer,
        TreeNode $shadowNode,
        CompilerContext $compilerContext,
    ): void {
        $node = $shadowNode->getNode();
        $supports = $transformer->supports($node);

        if ($supports) {
            $transformed = $transformer->enterNode($node, $compilerContext);
            $shadowNode->getParent()?->replaceChild($node, $transformed);
        }

        foreach ($shadowNode->getChildren() as $shadowChild) {
            $this->traverseForTransformer($transformer, $shadowChild, $compilerContext);
        }

        if (!$supports) {
            return;
        }

        $transformed = $transformer->leaveNode($node, $compilerContext);
        if ($transformed !== null) {
            $shadowNode->getParent()?->replaceChild($node, $transformed);

            return;
        }

        $shadowNode->getParent()?->removeChild($node);
    }
}
