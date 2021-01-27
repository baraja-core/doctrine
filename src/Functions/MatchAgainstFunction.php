<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Sources:
 *
 * https://gist.github.com/ZeBigDuck/1234419/edaa13a851b1ea1e9926ec9c003ad762876ffe1d
 * http://stackoverflow.com/questions/17534796/match-against-script-is-not-working-with-symfony2
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

		if (strtolower($lexer->lookahead['value'] ?? '') !== 'against') {
			$parser->syntaxError('against');
		}

		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$this->against = $parser->StringPrimary();

		if (strtolower($lexer->lookahead['value'] ?? '') === 'boolean') {
			$parser->match(Lexer::T_IDENTIFIER);
			$this->booleanMode = true;
		}
		if (strtolower($lexer->lookahead['value'] ?? '') === 'expand') {
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
