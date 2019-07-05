<?php declare(strict_types=1);

namespace Rector\PHPStan\Rector\Node;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwareVarTagValueNode;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\PHPStan\Tests\Rector\Node\RemoveNonExistingVarAnnotationRector\RemoveNonExistingVarAnnotationRectorTest;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see RemoveNonExistingVarAnnotationRectorTest
 * @see https://github.com/phpstan/phpstan/commit/d17e459fd9b45129c5deafe12bca56f30ea5ee99#diff-9f3541876405623b0d18631259763dc1
 */
final class RemoveNonExistingVarAnnotationRector extends AbstractRector
{
    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    public function __construct(DocBlockManipulator $docBlockManipulator)
    {
        $this->docBlockManipulator = $docBlockManipulator;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Removes non-existing @var annotations above the code', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function get()
    {
        /** @var Training[] $trainings */
        return $this->getData();
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function get()
    {
        return $this->getData();
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Node::class];
    }

    /**
     * @param Node $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->shouldSkip($node)) {
            return null;
        }

        $variableName = $this->getVarTagVariableName($node);
        if ($variableName === null) {
            return null;
        }

        // it's there
        if (Strings::match($this->print($node), '#' . preg_quote($variableName, '#') . '\b#')) {
            return null;
        }

        $this->docBlockManipulator->removeTagFromNode($node, 'var');

        return $node;
    }

    private function shouldSkip(Node $node): bool
    {
        return ! $node instanceof Assign
            && ! $node instanceof AssignRef
            && ! $node instanceof Node\Stmt\Foreach_
            && ! $node instanceof Node\Stmt\Static_
            && ! $node instanceof Node\Stmt\Echo_
            && ! $node instanceof Node\Stmt\Return_
            && ! $node instanceof Node\Stmt\Expression
            && ! $node instanceof Node\Stmt\Throw_
            && ! $node instanceof Node\Stmt\If_
            && ! $node instanceof Node\Stmt\While_
            && ! $node instanceof Node\Stmt\Switch_
            && ! $node instanceof Node\Stmt\Nop;
    }

    private function getVarTagVariableName(Node $node): ?string
    {
        if (! $this->docBlockManipulator->hasTag($node, 'var')) {
            return null;
        }

        $varTag = $this->docBlockManipulator->getTagByName($node, 'var');

        /** @var AttributeAwareVarTagValueNode $varTagValue */
        $varTagValue = $varTag->value;

        return $varTagValue->variableName;
    }
}
