<?php
/**
 * Helper class for yform/manager plugin history context
 *
 * @category helper
 * @author Peter Schulze | p.schulze[at]bitshifters.de
 * @created 17.04.2024
 * @package redaxo\yform\manager
 */
class rex_yform_history_helper
{
    /**
     * detect diffs in 2 strings
     * @param $old
     * @param $new
     * @return array|array[]
     * @author Peter Schulze | p.schulze[at]bitshifters.de
     * @created 17.04.2024
     * @copyright https://github.com/paulgb/simplediff | Paul Butler (paulgb)
     */
    public static function diffStrings($old, $new):array
    {
        $matrix = array();
        $maxlen = 0;

        foreach ($old as $oindex => $ovalue) {
            $nkeys = array_keys($new, $ovalue);

            foreach ($nkeys as $nindex) {
                $matrix[$oindex][$nindex] =
                    isset($matrix[$oindex - 1][$nindex - 1]) ?
                    $matrix[$oindex - 1][$nindex - 1] + 1 :
                    1
                ;

                if ($matrix[$oindex][$nindex] > $maxlen) {
                    $maxlen = $matrix[$oindex][$nindex];
                    $omax = $oindex + 1 - $maxlen;
                    $nmax = $nindex + 1 - $maxlen;
                }
            }
        }

        if ($maxlen == 0) {
            return array(array('d' => $old, 'i' => $new));
        }

        return array_merge(
            self::diffStrings(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
            array_slice($new, $nmax, $maxlen),
            self::diffStrings(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen))
        );
    }

    /**
     * detect diffs in 2 strings and return as html
     * @param $old
     * @param $new
     * @return string
     * @author Peter Schulze | p.schulze[at]bitshifters.de
     * @created 17.04.2024
     * @copyright https://github.com/paulgb/simplediff | Paul Butler (paulgb)
     */
    public static function diffStringsToHtml($old, $new)
    {
        $ret = '';
        $diff = self::diffStrings(preg_split("/[\s]+/", $old), preg_split("/[\s]+/", $new));

        foreach ($diff as $k) {
            if (is_array($k)) {
                $ret .=
                    (!empty($k['d']) ? "<del>" . implode(' ', $k['d']) . "</del> " : '').
                    (!empty($k['i']) ? "<ins>" . implode(' ', $k['i']) . "</ins> " : '')
                ;
            } else {
                $ret .= $k . ' ';
            }
        }

        return $ret;
    }
}