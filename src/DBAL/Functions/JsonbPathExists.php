<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DBAL\Functions;

use Doctrine\ORM\Query\AST\ASTException;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;

/*
 * Idea from https://github.com/boldtrn/JsonbBundle
 */
class JsonbPathExists extends FunctionNode
{
    public Node|null $leftHandSide = null;
    public Node|null $rightHandSide = null;

    /**
     * @throws ASTException
     */
    public function getSql(SqlWalker $sqlWalker): string
    {
        // TODO: json always converted to jsonb, check if needed
        return sprintf('jsonb_path_exists(%s::jsonb, %s)',
            $this->leftHandSide->dispatch($sqlWalker),
            $this->rightHandSide->dispatch($sqlWalker),
        );
    }

    /**
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->leftHandSide = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->rightHandSide = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
