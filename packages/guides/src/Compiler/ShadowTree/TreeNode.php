<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\Compiler\ShadowTree;

use LogicException;
use phpDocumentor\Guides\Nodes\CompoundNode;
use phpDocumentor\Guides\Nodes\DocumentNode;
use phpDocumentor\Guides\Nodes\Node;

use function array_values;

/** @template TNode of Node */
final class TreeNode
{
    /** @var TreeNode<DocumentNode> */
    private TreeNode $root;

    /** @var self<Node>[] */
    private array $children = [];

    private function __construct(
        /** @var TNode */
        private Node $node,
        /** @var self<Node>|self<DocumentNode>|null */
        private self|null $parent = null,
    ) {
    }

    /** @return TreeNode<DocumentNode> */
    public static function createFromDocument(DocumentNode $document): self
    {
        $node = new self($document);
        $node->setChildren(self::createFromCompoundNode($document, $node));
        $node->setRoot($node);

        return $node;
    }

    /**
     * @param CompoundNode<Node> $node
     * @param self<Node>|self<DocumentNode>|null $parent
     *
     * @return TreeNode<Node>[]
     */
    private static function createFromCompoundNode(CompoundNode $node, self|null $parent): array
    {
        $children = [];
        foreach ($node->getChildren() as $child) {
            $children[] = self::createFromNode($child, $parent);
        }

        return $children;
    }

    /**
     * @param TValue $node
     * @param self<Node>|self<DocumentNode>|null $parent
     *
     * @return TreeNode<TValue>
     *
     * @template TValue of Node
     */
    private static function createFromNode(Node $node, self|null $parent = null): self
    {
        $treeNode = new self($node, $parent);
        if ($node instanceof CompoundNode === false) {
            return $treeNode;
        }

        $treeNode->setChildren(self::createFromCompoundNode($node, $treeNode));

        return $treeNode;
    }

    /** @param self<Node>[] $children */
    private function setChildren(array $children): void
    {
        foreach ($children as $child) {
            $child->parent = $this;
        }

        $this->children = $children;
    }

    /** @return self<DocumentNode> */
    public function getRoot(): self
    {
        return $this->root;
    }

    /** @param self<DocumentNode> $root */
    private function setRoot(self $root): void
    {
        $this->root = $root;
        foreach ($this->children as $child) {
            $child->setRoot($root);
        }
    }

    /** @return TNode */
    public function getNode(): Node
    {
        return $this->node;
    }

    /** @return  TreeNode<Node>[] */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(Node $child): void
    {
        if ($this->node instanceof CompoundNode === false) {
            throw new LogicException('Cannot add a child to a non-compound node');
        }

        $shadowNode = self::createFromNode($child, $this);
        $shadowNode->setRoot($this->root);
        $this->children[] = $shadowNode;
        $this->node->addChildNode($child);
    }

    /** @return self<Node>|self<DocumentNode>|null */
    public function getParent(): TreeNode|null
    {
        return $this->parent;
    }

    public function removeChild(Node $node): void
    {
        if ($this->node instanceof CompoundNode === false) {
            throw new LogicException('Cannot remove a child from a non-compound node');
        }

        foreach ($this->children as $key => $child) {
            if ($child->getNode() === $node) {
                unset($this->children[$key]);
                $child->parent = null;
                $newNode = $this->node->removeNode($key);
                $this->parent?->replaceChild($this->node, $newNode);
                $this->node = $newNode;
                break;
            }
        }

        $this->children = array_values($this->children);
    }

    public function replaceChild(Node $oldChildNode, Node $newChildNode): void
    {
        if ($this->node instanceof CompoundNode === false) {
            throw new LogicException('Cannot remove a child from a non-compound node');
        }

        foreach ($this->children as $key => $child) {
            if ($child->getNode() === $oldChildNode) {
                $child->node = $newChildNode;
                $newNode = $this->node->replaceNode($key, $newChildNode);
                $this->parent?->replaceChild($this->node, $newNode);
                $this->node = $newNode;
                break;
            }
        }
    }
}
