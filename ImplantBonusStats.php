<?php declare(strict_types=1);

namespace Nadybot\User\Modules\IMPQL_MODULE;

class ImplantBonusStats {
	/** @var string */
	public $slot = 'Faded';

	/** @var int */
	public $buff;

	/** @var int[] */
	public $range;

	public function __construct(int $slot) {
		if ($slot === ImpQLController::FADED) {
			$this->slot = 'Faded';
		} elseif ($slot === ImpQLController::BRIGHT) {
			$this->slot = 'Bright';
		} else {
			$this->slot = 'Shiny';
		}
	}
}
