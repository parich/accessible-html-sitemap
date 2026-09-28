<?php
/**
 * Thai dictionary order for titles and names.
 *
 * Databases sort Thai by character code, so a word that starts with a leading
 * vowel (เ แ โ ใ ไ) comes after every other Thai word: "ใจ" after "ฮา". Thai
 * dictionaries read a leading vowel after the consonant it is written before,
 * so "เกม" and "ไก่" belong with the ก words. Lists with at least one Thai name
 * are sorted again here; any other list keeps the order the database gave it.
 *
 * @package Parich_Accessible_Sitemap
 */

if (!defined('ABSPATH')) {
	exit;
}

class Parich_Sitemap_Thai_Sort
{
	/**
	 * @param array         $items
	 * @param callable      $name       function ($item): string, the text to sort by.
	 * @param bool          $descending
	 * @param callable|null $group      function ($item): int, compared before the name
	 *                                  (ascending), e.g. a page's menu_order.
	 * @return array The items as a list: in Thai order, or in the order given when no
	 *               name contains Thai or the parich_sitemap_thai_sort filter says no.
	 */
	public static function sort(array $items, callable $name, $descending = false, $group = null)
	{
		$items = array_values($items);
		$names = array_map(function ($item) use ($name) {
			return (string) $name($item);
		}, $items);

		/**
		 * Filters whether lists containing Thai are sorted in Thai dictionary order.
		 *
		 * @param bool $sort Default true.
		 */
		if (!self::has_thai($names) || !apply_filters('parich_sitemap_thai_sort', true)) {
			return $items;
		}

		$keys = self::sort_keys($names);
		$groups = $group ? array_map($group, $items) : array();
		$order = array_keys($items);
		usort($order, function ($a, $b) use ($keys, $groups, $descending) {
			if ($groups && $groups[$a] !== $groups[$b]) {
				return $groups[$a] < $groups[$b] ? -1 : 1;
			}
			$result = strcmp($keys[$a], $keys[$b]);
			if ($descending) {
				$result = -$result;
			}
			// Equal names keep the order they came in (usort is not stable before PHP 8).
			return $result ?: $a - $b;
		});

		return array_map(function ($index) use ($items) {
			return $items[$index];
		}, $order);
	}

	/**
	 * @param string[] $names
	 */
	public static function has_thai(array $names)
	{
		foreach ($names as $name) {
			if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $name)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keys that sort with strcmp(): from the intl Collator (ICU's Thai rules) when PHP
	 * has it, otherwise from fallback_key().
	 *
	 * @param string[] $names
	 * @return string[]
	 */
	private static function sort_keys(array $names)
	{
		$collator = null;
		if (class_exists('Collator')) {
			try {
				$collator = new Collator('th_TH');
			} catch (Throwable $error) {
				$collator = null;
			}
		}

		/**
		 * Filters the collator for Thai order: a Collator, or null for the built-in fallback.
		 *
		 * @param Collator|null $collator
		 */
		$collator = apply_filters('parich_sitemap_collator', $collator);

		if ($collator instanceof Collator) {
			$keys = array();
			foreach ($names as $name) {
				$key = $collator->getSortKey($name);
				if (!is_string($key)) {
					// Not valid UTF-8: one kind of key for the whole list.
					$keys = null;
					break;
				}
				$keys[] = $key;
			}
			if (null !== $keys) {
				return $keys;
			}
		}

		return array_map(array(__CLASS__, 'fallback_key'), $names);
	}

	/**
	 * Sort key without intl, close to the Royal Institute dictionary order:
	 * - a leading vowel is read after the consonant it is written before (เกม: ก เ ม);
	 * - tone marks and the other signs above a letter, ฯ, ๆ, spaces and punctuation
	 *   only decide between words that are otherwise equal (กา, ก่า, ก้า);
	 * - digits first (Thai digits as 0–9), then Thai, then Latin letters regardless of
	 *   case and accents — the same as ICU's Thai rules.
	 * Thai consonants and vowels are otherwise in dictionary order in Unicode already.
	 * The key is compared byte by byte; it is not meant to be readable text.
	 *
	 * @param string $name
	 * @return string
	 */
	public static function fallback_key($name)
	{
		$text = remove_accents((string) $name);
		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$text = strtr($text, array(
			'๐' => '0',
			'๑' => '1',
			'๒' => '2',
			'๓' => '3',
			'๔' => '4',
			'๕' => '5',
			'๖' => '6',
			'๗' => '7',
			'๘' => '8',
			'๙' => '9',
		));
		$text = preg_replace('/([\x{0E40}-\x{0E44}])([\x{0E01}-\x{0E2E}])/u', '$2$1', $text) ?? $text;
		// Signs, spaces and punctuation (with ฯ and ๆ, which ICU also skips) out of the base.
		$base = preg_replace('/[\x{0E2F}\x{0E46}-\x{0E4E}\p{Z}\p{P}]/u', '', $text) ?? $text;
		// Latin letters after Thai: a byte above the first byte of any Thai character.
		$base = preg_replace('/[a-z]/', "\xF0" . '$0', $base) ?? $base;

		// "\0" ends the base, so a shorter word sorts first and the signs only break ties.
		return $base . "\0" . $text;
	}
}
