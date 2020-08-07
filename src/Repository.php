<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\EntityRepository;

final class Repository extends EntityRepository
{

	/**
	 * @return mixed[]
	 */
	public function findPairs(string $value, ?string $key = null): array
	{
		$return = [];
		$key = $key ?? 'id';

		$categories = $this->createQueryBuilder('e')
			->select('e.' . $key, 'e.' . $value)
			->getQuery()
			->getArrayResult();

		foreach ($categories as $category) {
			$return[$category[$key]] = $category[$value];
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
			->select('e.' . $key, 'e.' . $value);

		foreach ($conditions as $condition) {
			$selection->andWhere($condition);
		}

		$return = [];
		foreach ($selection->getQuery()->getArrayResult() as $item) {
			if ($key === null) {
				$return[] = $item[$value];
			} else {
				$return[$item[$key]] = $item[$value];
			}
		}

		return $return;
	}
}
