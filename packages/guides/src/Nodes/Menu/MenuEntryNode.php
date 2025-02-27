<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\Nodes\Menu;

use phpDocumentor\Guides\Nodes\AbstractNode;
use phpDocumentor\Guides\Nodes\TitleNode;

/** @extends AbstractNode<TitleNode> */
final class MenuEntryNode extends AbstractNode
{
    /** @var MenuEntryNode[] */
    private array $sections = [];

    /** @param MenuEntryNode[] $children */
    public function __construct(
        private readonly string $url,
        TitleNode $title,
        private readonly array $children = [],
        private readonly bool $isDocumentRoot = false,
        private readonly int $level = 1,
        private readonly string $anchor = '',
    ) {
        $this->value = $title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAnchor(): string
    {
        return $this->anchor;
    }

    /** @return MenuEntryNode[] */
    public function getChildren(): array
    {
        return $this->children;
    }

    /** @return MenuEntryNode[] */
    public function getEntries(): array
    {
        return $this->children;
    }

    public function isDocumentRoot(): bool
    {
        return $this->isDocumentRoot;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    /** @return MenuEntryNode[] */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function addSection(MenuEntryNode $section): void
    {
        $this->sections[] = $section;
    }
}
