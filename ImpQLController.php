<?php

namespace Budabot\User\Modules\IMPQL_MODULE;

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'impql',
 *		accessLevel = 'all',
 *		description = 'Get information about the QL of an implant',
 *		help        = 'impql.txt'
 *	)
 */
class ImpQLController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 * @var string $moduleName
	 */
	public $moduleName;

	/**
	 * @var \Budabot\Core\Budabot $chatBot
	 * @Inject
	 */
	public $chatBot;

	/**
	 * @var \Budabot\Core\Text $text
	 * @Inject
	 */
	public $text;

	const FADED = 0;
	const BRIGHT = 1;
	const SHINY = 2;

	const ATTRIBUTE = 0;
	const TREATMENT = 1;
	const TITLE_LEVEL = 2;

	const REGULAR = 'reqRegular';
	const JOBE = 'reqJobe';

	protected $implantBreakpoints = [
		'skills' => [
			  1 => [ 2,  3,   6],
			200 => [42, 63, 105],
			201 => [42, 63, 106],
			300 => [57, 85, 141],
		],
		'abilities' => [
			  1 => [ 2,  3,  5],
			200 => [22, 33, 55],
			201 => [22, 33, 55],
			300 => [29, 44, 73],
		],
		'reqRegular' => [
			  1 => [   6,   11, 0],
			200 => [ 404,  951, 0],
			201 => [ 426, 1001, 0],
			300 => [1095, 2051, 0],
		],
		'reqJobe' => [
			  1 => [  16,   11, 3],
			200 => [ 414, 1005, 4],
			201 => [ 476, 1001, 5],
			300 => [1231, 2051, 6],
		],
	];

	/**
	 * Try to determine the bonus for an interpolated QL
	 *
	 * @param array $itemSpecs An associative array [QLX => bonus X, QLY => bonus Y]
	 * @param int $searchedQL The QL we want to interpolate to
	 * @return int|null The interpolated bonus at the given QL or null if out of range
	 */
	public function calcStatFromQL(array $itemSpecs, int $searchedQL): ?int {
		$lastSpec = null;
		foreach ($itemSpecs as $itemQL => $itemBonus) {
			if ($lastSpec === null) {
				$lastSpec = [$itemQL, $itemBonus];
			} else {
				if ($lastSpec[0] <= $searchedQL && $itemQL >= $searchedQL) {
					$multi = (1 / ($itemQL - $lastSpec[0]));
					return round($lastSpec[1] + ( ($itemBonus-$lastSpec[1]) * ($multi *($searchedQL-($lastSpec[0]-1)-1))));
				} else {
					$lastSpec = [$itemQL, $itemBonus];
				}
			}
		}
		return null;
	}

	/**
	 * Try to find the lowest QL that gives a bonus
	 *
	 * @param int   $bonus     The bonus you want to reach
	 * @param array $itemSpecs An associative array with ql => bonus
	 *
	 * @return int The lowest QL that gives that bonus
	 */
	public function findBestQLForBonus(int $bonus, array $itemSpecs): int {
		for ($searchedQL = min(array_keys($itemSpecs)); $searchedQL <= max(array_keys($itemSpecs)); $searchedQL++) {
			$value = $this->calcStatFromQL($itemSpecs, $searchedQL);
			if ($value === null) {
				continue;
			}
			if ($value > $bonus) {
				return $searchedQL-1;
			}
		}
		return $searchedQL-1;
	}

	/**
	 * Get a single breakpoint-spec from the internal breakpoint list
	 *
	 * @param string $type     The name of the breakpoint ("abilities", "reqRegular", ...)
	 * @param int    $position The position in the list (usually 0, 1 or 2)
	 *
	 * @return array An associative array in the form [QL => bonus/requirement]
	 */
	protected function getBreakpoints(string $type, int $position): array {
		return array_map(
			function(array $item) use ($position) {
				return $item[$position];
			},
			$this->implantBreakpoints[$type]
		);
	}

	/**
	 * Find the highest implant QL you can equip with given attribute and treatment
	 *
	 * @param int    $attributeLevel How much of the implant's attribute do you have?
	 * @param int    $treatmentLevel How much treatment do you have?
	 * @param string $type           self::REGULAR or self::JOBE
	 *
	 * @return int The highest usable implant QL
	 */
	public function findHighestImplantQL(int $attributeLevel, int $treatmentLevel, string $type): int {
		$attributeBreakpoints = $this->getBreakpoints($type, self::ATTRIBUTE);
		$treatmentBreakpoints = $this->getBreakpoints($type, self::TREATMENT);
		$bestAttribQL = $this->findBestQLForBonus($attributeLevel, $attributeBreakpoints);
		$bestTreatmentQL = $this->findBestQLForBonus($treatmentLevel, $treatmentBreakpoints);

		return min($bestAttribQL, $bestTreatmentQL);
	}

	/**
	 * Find the highest regular implant QL you can equip with given attribute and treatment
	 *
	 * @param int $attributeLevel How much of the implant's attribute do you have?
	 * @param int $treatmentLevel How much treatment do you have?
	 *
	 * @return int The highest usable regular implant QL
	 */
	public function findHighestRegularImplantQL(int $attributeLevel, int $treatmentLevel): int {
		return $this->findHighestImplantQL($attributeLevel, $treatmentLevel, 'reqRegular');
	}

	/**
	 * Find the highest Jobe Implant QL you can equip with given attribute and treatment
	 *
	 * @param int    $attributeLevel How much of the implant's attribute do you have?
	 * @param int    $treatmentLevel How much treatment do you have?
	 *
	 * @return int The highest usable Jobe Implant QL
	 */
	public function findHighestJobeImplantQL(int $attributeLevel, int $treatmentLevel): int {
		return $this->findHighestImplantQL($attributeLevel, $treatmentLevel, 'reqJobe');
	}

	/**
	 * @HandlesCommand("impql")
	 * @Matches("/^impql\s+(\d+)\s+(\d+)$/i")
	 */
	public function impQlDetermineCommand($message, $channel, $sender, $sendto, $args) {
		$attrib = (int)$args[1];
		$treatment = (int)$args[2];
		$regularQL = $this->findHighestRegularImplantQL($attrib, $treatment);
		$jobeQL = $this->findHighestJobeImplantQL($attrib, $treatment);

		if ($regularQL === 0) {
			$msg = "Your pathetic stats aren't even enough for a QL 1 implant.";
			$sendto->reply($msg);
			return;
		}

		$regularBlob = $this->renderBlob(self::REGULAR, $regularQL);

		$msg = "With <highlight>$attrib<end> Ability ".
			"and <highlight>$treatment<end> Treatment, ".
			"the highest possible $regularBlob is QL <highlight>$regularQL<end>";
		if ($jobeQL >= 100) {
			$jobeBlob = $this->renderBlob(self::JOBE, $jobeQL);
			$msg .= " and the highest possible $jobeBlob is QL <highlight>$jobeQL<end>";
		}

		$sendto->reply($msg . ".");
	}

	/**
	 * @HandlesCommand("impql")
	 * @Matches("/^impql\s+(\d+)$/i")
	 */
	public function impQlCommand($message, $channel, $sender, $sendto, $args) {
		$ql = (int)$args[1];
		if ($ql < 1 || $ql > 300) {
			$msg = "Implants only exist is QLs between 1 and 300.";
			$sendto->reply($msg);
			return;
		}

		$regularBlob = $this->renderBlob(self::REGULAR, $ql);

		$msg = "QL <highlight>$ql<end> $regularBlob details";
		if ($ql >= 100) {
			$jobeBlob = $this->renderBlob(self::JOBE, $ql);
			$msg .= " and $jobeBlob details";
		}

		$sendto->reply($msg . ".");
	}

	/**
	 * Render a single bonus stat for a cluster type
	 *
	 * Roughly looks like this:
	 * 42 (QL 147 - QL 150) Shiny -> 306 / 720
	 *
	 * @param \Budabot\Modules\ImplantBonusStats $stats The stats to render
	 * @param string                             $type  "Shiny", "Bright" or "Faded"
	 *
	 * @return string the rendered line including newline
	 */
	protected function renderBonusLine(ImplantBonusStats $stats, string $type): string {
		$fromQL = $this->text->alignNumber($stats->range[0], 3, "highlight");
		$toQL   = $this->text->alignNumber($stats->range[1], 3, "highlight");

		$line = $this->text->alignNumber($stats->buff, 3, 'highlight').
			" (QL $fromQL - QL $toQL) " . $stats->slot;
		if ($stats->range[1] < 300) {
			$nextBest = $this->getImplantQLSpecs($type, $stats->range[1]+1);
			$line .= " <header>-><end> ".
				"<highlight>" . $nextBest->requirements->abilities . "<end>".
				" / ".
				"<highlight>" . $nextBest->requirements->treatment . "<end>";
		}
		return $line . "\n";
	}

	/**
	 * Render the popup-blob for a regular or jobe implant at a given QL
	 *
	 * @param string $type self::REGULAR or self::JOBE
	 * @param int    $ql   The QL to render for
	 *
	 * @return string the full link to the blob
	 */
	public function renderBlob(string $type, int $ql): string {
		$specs = $this->getImplantQLSpecs($type, $ql);
		$indent = "<tab>";

		$blob = "<header2>Requirements to wear:<end>\n".
			$indent.$this->text->alignNumber($specs->requirements->abilities, 4, 'highlight').
			" Ability\n".
			$indent.$this->text->alignNumber($specs->requirements->treatment, 4, 'highlight').
			" Treatment\n";

		if ($specs->requirements->titleLevel > 0) {
			$blob .= $indent.$this->text->alignNumber($specs->requirements->titleLevel, 4, 'highlight').
			" Title level\n";
		}

		$blob .= "\n<header2>Ability Clusters:<end>\n".
			$indent.$this->renderBonusLine($specs->abilities->shiny, $type).
			$indent.$this->renderBonusLine($specs->abilities->bright, $type).
			$indent.$this->renderBonusLine($specs->abilities->faded, $type)."\n";

		$blob .= "<header2>Skill Clusters:<end>\n".
			$indent.$this->renderBonusLine($specs->skills->shiny, $type).
			$indent.$this->renderBonusLine($specs->skills->bright, $type).
			$indent.$this->renderBonusLine($specs->skills->faded, $type)."\n";

		$blob .= "\n\n";

		$buildMultiplier = [4, 3, 2];
		if ($type === self::JOBE) {
			$buildMultiplier = [6.25, 4.75, 3.25];
			if ($ql > 200) {
				$buildMultiplier = [6.75, 5.25, 3.75];
			}
		} elseif ($ql > 200) {
			$buildMultiplier = [5.25, 4.35, 2.55];
		}
		$blob .= "<header2>Requirements to build:<end>\n".
			$indent.$this->text->alignNumber(floor($buildMultiplier[0] * $ql), 4, 'highlight').
			" NP for Shiny\n".
			$indent.$this->text->alignNumber(floor($buildMultiplier[1] * $ql), 4, 'highlight').
			" NP for Bright\n".
			$indent.$this->text->alignNumber(floor($buildMultiplier[2] * $ql), 4, 'highlight').
			" NP for Faded\n\n";

		$blob .= "<header2>Requirements to clean:<end>\n";
		if ($type === self::JOBE) {
			$blob .= $indent . "Jobe Implants cannot be cleaned.\n\n";
		} elseif ($ql > 200) {
			$blob .= $indent . "Refined Implants cannot be cleaned.\n\n";
		} else {
			$blob .= $indent.$this->text->alignNumber($ql, 4, 'highlight') . " NanoProgramming\n".
				$indent.$this->text->alignNumber(floor(4.75*$ql), 4, 'highlight') . " Break&Entry\n\n";
		}

		$minQL = 1;
		if ($ql >= 201) {
			$minQL = 201;
		}
		$blob .= "<header2>Minimum Cluster QL:<end>\n".
			$indent.$this->text->alignNumber(max($minQL, floor(0.86*$ql)), 3, 'highlight') . " Shiny\n".
			$indent.$this->text->alignNumber(max($minQL, floor(0.84*$ql)), 3, 'highlight') . " Bright\n".
			$indent.$this->text->alignNumber(max($minQL, floor(0.82*$ql)), 3, 'highlight') . " Faded\n\n";
	
		$impName = "Implant";
		if ($type === self::JOBE) {
			if ($ql >= 201) {
				$impName = "Implant with shiny Jobe cluster";
			} else {
				$impName = "Jobe Implant";
			}
		}
		return $this->text->makeBlob($impName, $blob, "QL $ql $impName Details");
	}

	/**
	 * Returns the min- and max-ql for an implant to return a bonus
	 *
	 * @param string $type  The cluster type ("skill" or "abililities")
	 * @param int    $slot  The cluster slot type (0 => faded, 1 => bright, 2 => shiny)
	 * @param int    $bonus The bonus for which to return the QL-range
	 *
	 * @return int[] An array with the min- and the max-ql
	 */
	public function getBonusQLRange(string $type, int $slot, int $bonus): ?array {
		$breakpoints = $this->getBreakpoints($type, $slot);
		$minQL = min(array_keys($breakpoints));
		$maxQL = max(array_keys($breakpoints));
		$foundMinQL = 0;
		$foundMaxQL = 300;
		for ($ql = $minQL; $ql <= $maxQL; $ql++) {
			$statBonus = $this->calcStatFromQL($breakpoints, $ql);
			if ($statBonus > $bonus) {
				return [$foundMinQL, $foundMaxQL];
			} elseif ($statBonus === $bonus) {
				$foundMaxQL = $ql;
				if ($foundMinQL === 0) {
					$foundMinQL = $ql;
				}
			}
		}
		if ($statBonus === $bonus) {
			return [$foundMinQL, $foundMaxQL];
		}
		return null;
	}

	/**
	 * Get the bonus stats for an implant slot and ql
	 *
	 * @param string $type Type of bonus ("skills" or "abilities")
	 * @param int    $slot 0 => faded, 1 => bright, 2 => shiny
	 * @param int    $ql   The QL of the implant
	 * @return \Budabot\User\Modules\ImplantBonusStats
	 */
	protected function getBonusStatsForType(string $type, int $slot, int $ql): ImplantBonusStats {
		$breakpoints = $this->getBreakpoints($type, $slot);
		$buff = $this->calcStatFromQL($breakpoints, $ql);
		$stats = new ImplantBonusStats($slot);
		$stats->buff = $buff;
		$stats->range = $this->getBonusQLRange($type, $slot, $buff);
		return $stats;
	}

	/**
	 * Get all specs of an implant at a certain ql
	 *
	 * @param string $type self::JOBE or self::REGULAR
	 * @param int    $ql   The QL of the implant you want to build
	 *
	 * @return \Budabot\User\Modules\ImplantSpecs
	 */
	public function getImplantQLSpecs(string $type, int $ql): ImplantSpecs {
		$specs = new ImplantSpecs();
		$specs->ql = $ql;

		$treatmentBreakpoints = $this->getBreakpoints($type, self::TREATMENT);
		$attributeBreakpoints = $this->getBreakpoints($type, self::ATTRIBUTE);
		$tlBreakpoints = $this->getBreakpoints($type, self::TITLE_LEVEL);

		$requirements = new ImplantRequirements();
		$requirements->treatment = $this->calcStatFromQL($treatmentBreakpoints, $ql);
		$requirements->abilities = $this->calcStatFromQL($attributeBreakpoints, $ql);
		$requirements->titleLevel = $this->calcStatFromQL($tlBreakpoints, $ql);
		$specs->requirements = $requirements;

		$skills = new ImplantBonusTypes();
		$skills->faded  = $this->getBonusStatsForType('skills', self::FADED, $ql);
		$skills->bright = $this->getBonusStatsForType('skills', self::BRIGHT, $ql);
		$skills->shiny  = $this->getBonusStatsForType('skills', self::SHINY, $ql);
		$specs->skills = $skills;

		$abilities = new ImplantBonusTypes();
		$abilities->faded  = $this->getBonusStatsForType('abilities', self::FADED, $ql);
		$abilities->bright = $this->getBonusStatsForType('abilities', self::BRIGHT, $ql);
		$abilities->shiny  = $this->getBonusStatsForType('abilities', self::SHINY, $ql);
		$specs->abilities = $abilities;

		return $specs;
	}
}

class ImplantSpecs {
	/** @var int */
	public $ql;

	/** @var \Budabot\User\Modules\ImplantRequirements */
	public $requirements;

	/** @var \Budabot\User\Modules\ImplantBonusTypes */
	public $skills;

	/** @var \Budabot\User\Modules\ImplantBonusTypes */
	public $abilities;
}

class ImplantRequirements {
	/** @var int */
	public $treatment;

	/** @var int */
	public $abilities;

	/** @var int */
	public $titleLevel;
}

class ImplantBonusTypes {
	/** @var \Budabot\User\Modules\ImplantBonusStats */
	public $faded;

	/** @var \Budabot\User\Modules\ImplantBonusStats */
	public $bright;

	/** @var \Budabot\User\Modules\ImplantBonusStats */
	public $shiny;
}

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
