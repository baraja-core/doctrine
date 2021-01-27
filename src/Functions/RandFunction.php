<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

final class RandFunction extends FunctionNode
{
	public function parse(Parser $parser): void
	{
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}


	public function getSql(SqlWalker $sqlWalker): string
	{
		return 'RAND()';
	}
}
