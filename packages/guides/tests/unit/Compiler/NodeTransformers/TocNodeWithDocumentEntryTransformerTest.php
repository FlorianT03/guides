<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\Compiler\NodeTransformers;

use phpDocumentor\Guides\Compiler\CompilerContext;
use phpDocumentor\Guides\Nodes\DocumentNode;
use phpDocumentor\Guides\Nodes\DocumentTree\DocumentEntryNode;
use phpDocumentor\Guides\Nodes\Menu\TocNode;
use phpDocumentor\Guides\Nodes\ProjectNode;
use phpDocumentor\Guides\Nodes\TitleNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TocNodeWithDocumentEntryTransformerTest extends TestCase
{
    /** @param string[] $paths */
    private static function getCompilerContext(string $currentPath, array $paths): CompilerContext
    {
        $projectNode = new ProjectNode();
        $documentEntries = [];
        foreach ($paths as $path) {
            $documentEntries[] = new DocumentEntryNode($path, TitleNode::emptyNode());
        }

        $projectNode->setDocumentEntries($documentEntries);
        $context = new CompilerContext($projectNode);

        return $context->withShadowTree(new DocumentNode('123', $currentPath));
    }

    /**
     * @param string[] $paths
     * @param string[] $tocFiles
     */
    #[DataProvider('tocTreeEntryProvider')]
    public function testTocTreeEntryCount(string $currentPath, array $paths, array $tocFiles, int $expectedCount, bool $glob = false): void
    {
        $context = self::getCompilerContext($currentPath, $paths);

        $node = new TocNode($tocFiles);
        if ($glob) {
            $node = $node->withOptions(['glob' => true]);
        }

        $transformer = new TocNodeWithDocumentEntryTransformer();

        $result = $transformer->leaveNode($node, $context);
        self::assertInstanceOf(TocNode::class, $result);
        $menuEntries = $result->getMenuEntries();
        self::assertCount($expectedCount, $menuEntries);
    }

    /** @return array<string, array<string, array<int, string>|bool|int|string>> */
    public static function tocTreeEntryProvider(): array
    {
        return [
            'testAbsoluteTocUrl' => [
                'current' => 'index',
                'paths' =>  ['index', 'doc1', 'doc2', 'doc1/subdoc', 'doc3/doc1'],
                'tocFiles' => ['/doc1', '/doc2'],
                'expectedCount' => 2,
            ],
            'testRelativeTocUrl' => [
                'current' => 'index',
                'paths' =>  ['index', 'doc1', 'doc2', 'doc1/subdoc', 'doc3/doc1'],
                'tocFiles' => ['doc1', 'doc2'],
                'expectedCount' => 2,
            ],
            'testRelativeTocUrlInSubdir' => [
                'current' => 'doc1/index',
                'paths' =>  ['index', 'doc1', 'doc2','doc1/index' , 'doc1/subdoc1', 'doc1/subdoc2', 'doc1/subdoc3', 'doc3/index'],
                'tocFiles' => ['subdoc1', 'subdoc1'],
                'expectedCount' => 2,
            ],
            'testAbsoluteGlob' => [
                'current' => 'index',
                'paths' =>  ['index', 'doc1', 'doc2', 'doc1/subdoc', 'doc3/doc1'],
                'tocFiles' => ['/*'],
                'expectedCount' => 2,
                'glob' => true,
            ],
            'testAbsoluteGlobFromSubdir' => [
                'current' => 'doc1/subdoc',
                'paths' =>  ['index', 'doc1', 'doc2', 'doc1/subdoc', 'doc3/doc1'],
                'tocFiles' => ['/*'],
                'expectedCount' => 3,
                'glob' => true,
            ],
            'testRelativeGlob' => [
                'current' => 'index',
                'paths' =>  ['index', 'doc1', 'doc2', 'doc1/subdoc', 'doc3/doc1'],
                'tocFiles' => ['*'],
                'expectedCount' => 2,
                'glob' => true,
            ],
            'testRelativeGlobInSubdir' => [
                'current' => 'doc1/index',
                'paths' =>  ['index', 'doc1', 'doc2','doc1/index' , 'doc1/subdoc1', 'doc1/subdoc2', 'doc1/subdoc3', 'doc3/index'],
                'tocFiles' => ['*'],
                'expectedCount' => 3,
                'glob' => true,
            ],
            'testPartialGlob' => [
                'current' => 'index',
                'paths' =>  ['index', 'feature1', 'feature2','feature/index' , 'deprecation1', 'deprecation2'],
                'tocFiles' => ['feature*'],
                'expectedCount' => 2,
                'glob' => true,
            ],
            'testPartialPathGlob' => [
                'current' => 'index',
                'paths' =>  ['index', 'feature/file1', 'feature/file2', 'feature/index' , 'deprecation/file1', 'deprecation/file2','deprecation/index'],
                'tocFiles' => ['*/file1'],
                'expectedCount' => 2,
                'glob' => true,
            ],
        ];
    }
}
