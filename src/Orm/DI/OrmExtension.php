<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\DI;


use Baraja\Doctrine\DBAL\DI\DbalConsoleExtension;
use Baraja\Doctrine\ORM\EntityManagerDecorator;
use Baraja\Doctrine\ORM\Mapping\ContainerEntityListenerResolver;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers;
use Nette\InvalidArgumentException;

final class OrmExtension extends CompilerExtension
{

	/** @var mixed[] */
	private array $defaults = [
		'entityManagerDecoratorClass' => EntityManagerDecorator::class,
		'configurationClass' => Configuration::class,
		'configuration' => [
			'proxyDir' => '%tempDir%/proxies',
			'autoGenerateProxyClasses' => null,
			'proxyNamespace' => 'Baraja\Doctrine\Proxy',
			'metadataDriverImpl' => null,
			'entityNamespaces' => [],
			'customStringFunctions' => [],
			'customNumericFunctions' => [],
			'customDatetimeFunctions' => [],
			'customHydrationModes' => [],
			'classMetadataFactoryName' => null,
			'defaultRepositoryClassName' => null,
			'namingStrategy' => UnderscoreNamingStrategy::class,
			'quoteStrategy' => null,
			'entityListenerResolver' => null,
			'repositoryFactory' => null,
			'defaultQueryHints' => [],
		],
	];


	/**
	 * @return string[]
	 */
	public static function mustBeDefinedAfter(): array
	{
		return [DbalConsoleExtension::class];
	}


	public function loadConfiguration(): void
	{
		$this->loadDoctrineConfiguration();
	}


	public function loadDoctrineConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = array_replace($this->defaults['configuration'], $this->config);
		$config = Helpers::expand($config, $builder->parameters);

		$configurationClass = $config['configurationClass'] ?? Configuration::class;

		if (!is_a($configurationClass, Configuration::class, true)) {
			throw new InvalidArgumentException('Configuration class must be subclass of ' . Configuration::class . ', ' . $configurationClass . ' given.');
		}

		$configuration = $builder->addDefinition($this->prefix('configuration'))
			->setType(is_object($configurationClass) ? (string) get_class($configurationClass) : $configurationClass);

		if ($config['proxyDir'] !== null) {
			$configuration->addSetup('setProxyDir', [$config['proxyDir']]);
		}
		if ($config['autoGenerateProxyClasses'] !== null) {
			$configuration->addSetup('setAutoGenerateProxyClasses', [$config['autoGenerateProxyClasses']]);
		}
		if ($config['proxyNamespace'] !== null) {
			$configuration->addSetup('setProxyNamespace', [$config['proxyNamespace']]);
		}
		if ($config['metadataDriverImpl'] !== null) {
			$configuration->addSetup('setMetadataDriverImpl', [$config['metadataDriverImpl']]);
		}
		if ($config['entityNamespaces']) {
			$configuration->addSetup('setEntityNamespaces', [$config['entityNamespaces']]);
		}

		// Custom functions
		$configuration
			->addSetup('setCustomStringFunctions', [$config['customStringFunctions']])
			->addSetup('setCustomNumericFunctions', [$config['customNumericFunctions']])
			->addSetup('setCustomDatetimeFunctions', [$config['customDatetimeFunctions']])
			->addSetup('setCustomHydrationModes', [$config['customHydrationModes']]);

		if ($config['classMetadataFactoryName'] !== null) {
			$configuration->addSetup('setClassMetadataFactoryName', [$config['classMetadataFactoryName']]);
		}
		if ($config['defaultRepositoryClassName'] !== null) {
			$configuration->addSetup('setDefaultRepositoryClassName', [$config['defaultRepositoryClassName']]);
		}
		if ($config['namingStrategy'] !== null) {
			$configuration->addSetup('setNamingStrategy', [new Statement(
				$config['namingStrategy'],
				$config['namingStrategy'] === UnderscoreNamingStrategy::class
					? [CASE_LOWER, true]
					: [],
			)]);
		}
		if ($config['quoteStrategy'] !== null) {
			$configuration->addSetup('setQuoteStrategy', [$config['quoteStrategy']]);
		}
		if ($config['entityListenerResolver'] !== null) {
			$configuration->addSetup('setEntityListenerResolver', [$config['entityListenerResolver']]);
		} else {
			$builder->addDefinition($this->prefix('entityListenerResolver'))
				->setType(ContainerEntityListenerResolver::class);
			$configuration->addSetup('setEntityListenerResolver', [$this->prefix('@entityListenerResolver')]);
		}
		if ($config['repositoryFactory'] !== null) {
			$configuration->addSetup('setRepositoryFactory', [$config['repositoryFactory']]);
		}
		if ($config['defaultQueryHints']) {
			$configuration->addSetup('setDefaultQueryHints', [$config['defaultQueryHints']]);
		}
	}
}
