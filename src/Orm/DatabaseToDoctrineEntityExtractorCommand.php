<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\Cache\ArrayCache;
use Doctrine\Common\ClassLoader;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\ORM\Tools\EntityGenerator;
use InvalidArgumentException;
use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @deprecated because internal Doctrine class EntityGenerator is being removed from the ORM and won't have any replacement
 */
final class DatabaseToDoctrineEntityExtractorCommand extends Command
{
	public function __construct(
		private string $rootDir,
		private EntityManagerInterface $entityManager
	) {
		parent::__construct();
		$realPath = realpath($rootDir);
		$this->rootDir = $realPath === false ? $rootDir : $realPath;
	}


	public function configure(): void
	{
		$this->setName('orm:database-to-doctrine-entity-extractor')
			->setDescription('Scan current database schema and generate valid Doctrine entities.')
			->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Entity namespace.')
			->addOption('path', null, InputOption::VALUE_REQUIRED, 'Where generated entities will be stored.');
	}


	public function execute(InputInterface $input, OutputInterface $output): int
	{
		echo '-------------------------' . "\n";
		echo 'CRITICAL WARNING' . "\n\n";
		echo 'This tool is deprecated, because internal Doctrine class EntityGenerator is being removed from the ORM and won\'t have any replacement.';
		echo "\n\n";

		if (($namespace = $input->getOption('namespace')) === null) {
			throw new InvalidArgumentException('Option "namespace" is required.');
		}
		if (\is_string($namespace) === false) {
			throw new InvalidArgumentException('Option "namespace" must be string.');
		}
		if (($path = $input->getOption('path')) === null) {
			throw new InvalidArgumentException('Option "path" is required.');
		}
		if (\is_string($path) === false) {
			throw new InvalidArgumentException('Option "path" must be string.');
		}

		$entityNamespace = (string) preg_replace_callback(
			'/(^(?:[a-z])|(?:\\\\[a-z]))/',
			fn(array $match): string => strtoupper((string) $match[1]),
			$namespace,
		);

		$output->writeln("\n");
		$output->writeln('Welcome to <comment>Baraja Database to Doctrine entity Extractor</comment>!');
		$output->writeln('<info>This tool analyzes your database and automatically generates valid entities.</info>');
		$output->writeln("\n");

		$helper = $this->getHelper('question');
		$questionNamespace = new ConfirmationQuestion(
			'Given namespace is "<comment>' . $namespace . '</comment>", '
			. 'so entity class-name should be "<comment>' . $entityNamespace . '\User</comment>" for example?',
			false,
		);
		$realPath = rtrim($this->rootDir . '/' . $path, '/');
		$questionPath = new ConfirmationQuestion(
			'Given path is "<comment>' . $path . '</comment>", '
			. 'your project root dir is "<comment>' . $this->rootDir . '</comment>", '
			. 'so entity can be stored in directory "<comment>' . $realPath . '</comment>"?',
			false,
		);
		$questionEntityDir = new ConfirmationQuestion('Can the "<comment>Entity</comment>" directory be added to the end of the path?', false);

		if ($helper->ask($input, $output, $questionNamespace) === false) {
			$output->writeln('<error>Please use different "--namespace" argument.</error>');

			return 1;
		}
		if ($helper->ask($input, $output, $questionPath) === false) {
			$output->writeln('<error>Please specify relative path in "--path" argument.</error>');

			return 1;
		}
		$realPath .= ($helper->ask($input, $output, $questionEntityDir) ? '/Entity' : '');

		FileSystem::createDir($realPath);
		$output->writeln("\n\n" . '<comment>Scaning your database...</comment>' . "\n\n");
		$connection = $this->entityManager->getConnection();

		$output->writeln('<info>Available tables</info> (database "<comment>' . $connection->getDatabase() . '</comment>"):');

		$showTablesStatement = $connection->executeQuery('SHOW TABLES');

		$tables = array_map(
			static fn(array $item): ?string => array_values($item)[0] ?? null,
			$showTablesStatement->fetchAllAssociative(),
		);

		$output->writeln('"<comment>' . implode('</comment>", "<comment>', $tables) . '</comment>".');
		$output->writeln('<info>Count tables:</info> ' . \count($tables));
		$output->writeln('Generating...');

		$classLoader = new ClassLoader('Entities', __DIR__);
		$classLoader->register();

		$classLoader = new ClassLoader('Proxies', __DIR__);
		$classLoader->register();

		$config = new Configuration;
		$config->setMetadataDriverImpl($config->newDefaultAnnotationDriver([__DIR__ . '/Entities']));
		$config->setMetadataCacheImpl(new ArrayCache);
		$config->setProxyDir(__DIR__ . '/Proxies');
		$config->setProxyNamespace('Proxies');

		$em = \Doctrine\ORM\EntityManager::create($connection, $config);

		// custom datatypes (not mapped for reverse engineering)
		$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'string');
		$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

		// fetch metadata
		$driver = new DatabaseDriver($em->getConnection()->getSchemaManager());
		$em->getConfiguration()->setMetadataDriverImpl($driver);

		$cmf = new DisconnectedClassMetadataFactory;
		$cmf->setEntityManager($em);
		/** @var ClassMetadataInfo[] $metadata */
		$metadata = $cmf->getAllMetadata();

		$generator = new EntityGenerator;
		$generator->setUpdateEntityIfExists(true);
		$generator->setGenerateStubMethods(true);
		$generator->setGenerateAnnotations(true);
		$generator->generate($metadata, $realPath);

		$output->writeln('Done. Formatting...');

		$entityFilePaths = glob($realPath . '/*.php');
		foreach (is_array($entityFilePaths) ? $entityFilePaths : [] as $entityFilePath) {
			$this->formatCodingStandard($entityFilePath, $entityNamespace);
		}

		$output->writeln('<info>All tasks successfully done.</info>');

		return 0;
	}


	private function formatCodingStandard(string $path, string $namespace): void
	{
		$content = FileSystem::read($path);

		// 1. Convert spaces to tabs
		$content = str_replace('    ', "\t", $content);

		// 2. Add strict declare and namespace
		$content = (string) preg_replace_callback('/^<\?php(\s+)use\s/', static fn(array $match): string => '<?php' . "\n\n"
				. 'declare(strict_types=1);' . "\n\n"
				. 'namespace ' . $namespace . ';' . "\n\n\n"
				. 'use ', $content);

		// 3. Fix relations to other entity
		$content = (string) preg_replace_callback('/@(param|return|var)\s(\\\\\w+)/', static fn(array $match): string => '@' . $match[1] . ' ' . (\class_exists($match[2]) ? $match[2] : trim($match[2], '\\')), $content);

		// 4. Fix namespace in relation annotation
		$content = (string) preg_replace_callback('/(@ORM\\\\\w+\(targetEntity=")([^"]+)"/', static fn(array $match): string => $match[1] . '\\' . $namespace . '\\' . $match[2] . '"', $content);

		// 5. Fix annotation type in setter
		$content = (string) preg_replace_callback('/(function\sset\w+)\((\\\\\w+)\s/', static fn(array $match): string => $match[1] . '(' . (\class_exists($match[2]) ? $match[2] : trim($match[2], '\\')) . ' ', $content);

		// 6. Add self typehint to setters
		$content = (string) preg_replace_callback('/(public\sfunction\s\w+[^)]+)\)(\s*[^:](?:\n|[^}])+?return\s\$this;)/', static fn(array $match): string => $match[1] . '): self' . $match[2], $content);

		FileSystem::write($path, $content);
	}
}
