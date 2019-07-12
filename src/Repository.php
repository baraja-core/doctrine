<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\ORM\EntityRepository;

class Repository extends EntityRepository
{

	/**
	 * @param string $value
	 * @param string|null $key
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
	 * @param string $value
	 * @param string[] $conditions
	 * @param string|null $key
	 * @return mixed[]
	 */
	public function findByConditions(string $value = 'id', array $conditions = [], ?string $key = 'id'): array
	{
		$return = [];

		$selection = $this->createQueryBuilder('e')
			->select('e.' . $key, 'e.' . $value);

		foreach ($conditions as $condition) {
			$selection->andWhere($condition);
		}

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