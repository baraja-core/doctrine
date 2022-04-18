<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\DI;


use Baraja\Doctrine\DBAL\DI\DbalConsoleExtension;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\ORM\Mapping\ContainerEntityListenerResolver;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers;
use Nette\InvalidArgumentException;

final class OrmExtension extends CompilerExtension
{
	private const DefaultConfiguration = [
		'configurationClass' => Configuration::class,
		'entityManagerClass' => EntityManager::class,
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
		$config = $this->getConfig();
		$configurationClass = $config['configurationClass'] ?? Configuration::class;

		if (!is_a($configurationClass, Configuration::class, true)) {
			throw new InvalidArgumentException(sprintf('Configuration class must be subclass of %s, %s given.', Configuration::class, $configurationClass));
		}

		$configuration = $builder->addDefinition($this->prefix('configuration'))
			->setType($configurationClass);

		$configuration->addSetup('setProxyDir', [$config['proxyDir']]);
		$configuration->addSetup('setProxyNamespace', [$config['proxyNamespace']]);
		if ($config['autoGenerateProxyClasses'] !== null) {
			$configuration->addSetup('setAutoGenerateProxyClasses', [$config['autoGenerateProxyClasses']]);
		}
		if ($config['metadataDriverImpl'] !== null) {
			$configuration->addSetup('setMetadataDriverImpl', [$config['metadataDriverImpl']]);
		}
		if ($config['entityNamespaces'] !== []) {
			$configuration->addSetup('setEntityNamespaces', [$config['entityNamespaces']]);
		}

		// Custom functions
		$configuration
			->addSetup('setCustomStringFunctions', [$config['customStringFunctions']])
			->addSetup('setCustomNumericFunctions', [$config['customNumericFunctions']])
			->addSetup('setCustomDatetimeFunctions', [$config['customDatetimeFunctions']])
			->addSetup('setCustomHydrationModes', [$config['customHydrationModes']])
			->addSetup('setNamingStrategy', [new Statement(
				$config['namingStrategy'],
				$config['namingStrategy'] === UnderscoreNamingStrategy::class
					? [CASE_LOWER, true]
					: [],
			)]);

		if ($config['classMetadataFactoryName'] !== null) {
			$configuration->addSetup('setClassMetadataFactoryName', [$config['classMetadataFactoryName']]);
		}
		if ($config['defaultRepositoryClassName'] !== null) {
			$configuration->addSetup('setDefaultRepositoryClassName', [$config['defaultRepositoryClassName']]);
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
		if ($config['defaultQueryHints'] !== []) {
			$configuration->addSetup('setDefaultQueryHints', [$config['defaultQueryHints']]);
		}
	}


	/**
	 * @return array{
	 *    configurationClass?: class-string,
	 *    entityManagerClass: class-string,
	 *    proxyDir: string,
	 *    autoGenerateProxyClasses: string|null,
	 *    proxyNamespace: string,
	 *    metadataDriverImpl: string|null,
	 *    entityNamespaces: array<int, string>,
	 *    customStringFunctions: array<int, string>,
	 *    customNumericFunctions: array<int, string>,
	 *    customDatetimeFunctions: array<int, string>,
	 *    customHydrationModes: array<int, int>,
	 *    classMetadataFactoryName: string|null,
	 *    defaultRepositoryClassName: string|null,
	 *    namingStrategy: class-string,
	 *    quoteStrategy: string|null,
	 *    entityListenerResolver: class-string|null,
	 *    repositoryFactory: class-string|null,
	 *    defaultQueryHints: mixed[],
	 * }
	 */
	public function getConfig(): array
	{
		$builder = $this->getContainerBuilder();
		assert(is_array($this->config));
		$config = array_replace(self::DefaultConfiguration['configuration'], $this->config);
		$config = Helpers::expand($config, $builder->parameters);

		/** @phpstan-ignore-next-line */
		return $config;
	}
}
