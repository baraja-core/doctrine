<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * @see http://stackoverflow.com/questions/17534796/match-against-script-is-not-working-with-symfony2
 * "MATCH_AGAINST" "(" {StateFieldPathExpression ","}* Literal ")"
 */
class MatchAgainstFunction extends FunctionNode
{
	/** @var PathExpression[] */
	protected ?array $pathExp = null;

	protected ?string $against = null;

	protected bool $booleanMode = false;

	protected bool $queryExpansion = false;


	public function parse(Parser $parser): void
	{
		// match
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);

		// first Path Expression is mandatory
		$this->pathExp = [];
		$this->pathExp[] = $parser->StateFieldPathExpression();

		// Subsequent Path Expressions are optional
		$lexer = $parser->getLexer();
		while ($lexer->isNextToken(Lexer::T_COMMA)) {
			$parser->match(Lexer::T_COMMA);
			$this->pathExp[] = $parser->StateFieldPathExpression();
		}

		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
		$lookHeadValue = strtolower($lexer->lookahead['value'] ?? '');

		if (in_array($lookHeadValue, ['against', 'boolean', 'expand'], true) === false) {
			$parser->syntaxError('against');
		}

		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$this->against = (string) $parser->StringPrimary();

		if ($lookHeadValue === 'boolean') {
			$parser->match(Lexer::T_IDENTIFIER);
			$this->booleanMode = true;
		}
		if ($lookHeadValue === 'expand') {
			$parser->match(Lexer::T_IDENTIFIER);
			$this->queryExpansion = true;
		}

		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}


	public function getSql(SqlWalker $walker): string
	{
		$fields = [];
		foreach ($this->pathExp ?? [] as $pathExp) {
			$fields[] = $pathExp->dispatch($walker);
		}

		$against = $walker->walkStringPrimary($this->against)
			. ($this->booleanMode ? ' IN BOOLEAN MODE' : '')
			. ($this->queryExpansion ? ' WITH QUERY EXPANSION' : '');

		return sprintf('MATCH (%s) AGAINST (%s)', implode(', ', $fields), $against);
	}
}
