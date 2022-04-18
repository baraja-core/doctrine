<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\EntityRepository;

final class Repository extends EntityRepository
{
	/**
	 * @return array<string, mixed>
	 */
	public function findPairs(string $value, ?string $key = null): array
	{
		$return = [];
		$key ??= 'id';

		/** @var array<int, array<string, string|int>> $categories */
		$categories = $this->createQueryBuilder('e')
			->select('e.' . $key, 'e.' . $value)
			->getQuery()
			->getArrayResult();

		foreach ($categories as $category) {
			$return[(string) $category[$key]] = $category[$value];
		}

		return $return;
	}


	/**
	 * @param string[] $conditions
	 * @return mixed[]
	 */
	public function findByConditions(string $value = 'id', array $conditions = [], ?string $key = 'id'): array
	{
		$selection = $this->createQueryBuilder('e')
			->select('e.' . $value);

		if ($key !== null) {
			$selection->addSelect('e.' . $key);
		}

		foreach ($conditions as $condition) {
			$selection->andWhere($condition);
		}

		/** @var array<string|int, array<non-empty-string, mixed>> $result */
		$result = $selection->getQuery()->getArrayResult();

		$return = [];
		foreach ($result as $item) {
			if ($key === null) {
				$return[] = $item[$value];
			} else {
				$return[$item[$key]] = $item[$value];
			}
		}

		return $return;
	}
}
