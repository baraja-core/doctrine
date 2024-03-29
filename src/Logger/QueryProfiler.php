<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use Tracy\IBarPanel;

final class QueryProfiler extends AbstractLogger implements IBarPanel
{
	public function getTab(): string
	{
		$timer = $this->getTimer();

		return '<span title="Doctrine database">'
			. '<svg viewBox="0 0 2048 2048"><path fill="#aaa" d="M1024 896q237 0 443-43t325-127v170q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-170q119 84 325 127t443 43zm0 768q237 0 443-43t325-127v170q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-170q119 84 325 127t443 43zm0-384q237 0 443-43t325-127v170q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-170q119 84 325 127t443 43zm0-1152q208 0 385 34.5t280 93.5 103 128v128q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-128q0-69 103-128t280-93.5 385-34.5z"></path></svg>'
			. '<span class="tracy-label">'
			. count($this->getEvents()) . ' queries'
			. ($timer > 0 ? ' / ' . sprintf('%0.1f', $timer * 1_000) . ' ms' : '')
			. '</span>'
			. '</span>';
	}


	public function getPanel(): string
	{
		if ($this->getEvents() === []) {
			return '';
		}
		$timer = $this->getTimer();

		return sprintf(
			'<h1>Queries: %s / %s</h1>',
			$this->getCounter(),
			($timer > 0 ? ', time: ' . sprintf('%0.3f', $timer * 1_000) . ' ms' : ''),
		);
	}
}
