<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\Query\AST\ArithmeticTerm;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\SimpleArithmeticExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

final class RoundFunction extends FunctionNode
{
	private SimpleArithmeticExpression|ArithmeticTerm|null $firstExpression = null;

	private Node|string|null $secondExpression = null;


	public function parse(Parser $parser): void
	{
		$lexer = $parser->getLexer();
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);

		/** @var SimpleArithmeticExpression|ArithmeticTerm $firstExpression */
		$firstExpression = $parser->SimpleArithmeticExpression();
		$this->firstExpression = $firstExpression;

		// parse second parameter if available
		if (Lexer::T_COMMA === $lexer->lookahead['type']) {
			$parser->match(Lexer::T_COMMA);
			$this->secondExpression = $parser->ArithmeticPrimary();
		}

		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}


	public function getSql(SqlWalker $sqlWalker): string
	{
		// use second parameter if parsed
		if ($this->secondExpression !== null) {
			return 'ROUND('
				. $this->firstExpression->dispatch($sqlWalker)
				. ', '
				. $this->secondExpression->dispatch($sqlWalker)
				. ')';
		}

		return 'ROUND(' . $this->firstExpression->dispatch($sqlWalker) . ')';
	}
}
