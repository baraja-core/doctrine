<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\Query\AST\ArithmeticTerm;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\SimpleArithmeticExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class GeoDistanceFunction extends FunctionNode
{
	private SimpleArithmeticExpression|ArithmeticTerm $lat1;

	private SimpleArithmeticExpression|ArithmeticTerm $lng1;

	private SimpleArithmeticExpression|ArithmeticTerm $lat2;

	private SimpleArithmeticExpression|ArithmeticTerm $lng2;


	public function getSql(SqlWalker $sqlWalker): string
	{
		/**
		 * @var SimpleArithmeticExpression $lat1
		 * @var SimpleArithmeticExpression $lat2
		 * @var SimpleArithmeticExpression $lng1
		 * @var SimpleArithmeticExpression $lng2
		 */
		[$lat1, $lat2, $lng1, $lng2] = [$this->lat1, $this->lat2, $this->lng1, $this->lng2];

		$lat1 = $sqlWalker->walkSimpleArithmeticExpression($lat1);
		$lat2 = $sqlWalker->walkSimpleArithmeticExpression($lat2);
		$lng1 = $sqlWalker->walkSimpleArithmeticExpression($lng1);
		$lng2 = $sqlWalker->walkSimpleArithmeticExpression($lng2);
		$kmPerLat2 = 6_372.795;
		$kmPerLng2 = 6_372.795;

		// sqrt(k1*(lat1-lat2)*(lat1-lat2) + k2*(lng1-lng2)*(lng1-lng2)
		return "(SQRT($kmPerLat2 * ($lat1 - $lat2) * ($lat1 - $lat2) + $kmPerLng2 * ($lng1 - $lng2) * ($lng1 - $lng2)))";
	}


	public function parse(Parser $parser): void
	{
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);

		$this->lat1 = $parser->SimpleArithmeticExpression();
		$parser->match(Lexer::T_COMMA);

		$this->lng1 = $parser->SimpleArithmeticExpression();
		$parser->match(Lexer::T_COMMA);

		$this->lat2 = $parser->SimpleArithmeticExpression();
		$parser->match(Lexer::T_COMMA);

		$this->lng2 = $parser->SimpleArithmeticExpression();

		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}
}
