<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\UI\Timetable;

/**
 * Timetable UI Colour Palette
 *
 * @version  v30
 * @since    v30
 */
class Palette
{
    protected $colors = [
        'gray' => [
            'background'   => 'bg-gray-200/90 dark:bg-gray-700/90',
            'text'         => 'text-gray-700 dark:text-gray-200',
            'textLight'    => 'text-gray-400 dark:text-gray-500',
            'textHover'    => 'hover:text-gray-800 dark:hover:text-gray-100',
            'outline'      => 'outline-gray-500 dark:outline-gray-400',
            'outlineLight' => 'outline-gray-500/50 dark:outline-gray-400/50',
            'outlineHover' => 'hover:outline-gray-600 dark:hover:outline-gray-300',
        ],
        'blue' => [
            'background'   => 'bg-blue-200 dark:bg-blue-800',
            'text'         => 'text-blue-900 dark:text-blue-100',
            'textLight'    => 'text-blue-400 dark:text-blue-500',
            'textHover'    => 'hover:text-blue-950 dark:hover:text-blue-50',
            'outline'      => 'outline-blue-700 dark:outline-blue-400',
            'outlineLight' => 'outline-blue-700/50 dark:outline-blue-400/50',
            'outlineHover' => 'hover:outline-blue-600 dark:hover:outline-blue-300',
        ],
        'cyan' => [
            'background'   => 'bg-cyan-200 dark:bg-cyan-800',
            'text'         => 'text-cyan-800 dark:text-cyan-100',
            'textLight'    => 'text-cyan-400 dark:text-cyan-500',
            'textHover'    => 'hover:text-cyan-950 dark:hover:text-cyan-50',
            'outline'      => 'outline-cyan-700 dark:outline-cyan-400',
            'outlineLight' => 'outline-cyan-700/50 dark:outline-cyan-400/50',
            'outlineHover' => 'hover:outline-cyan-600 dark:hover:outline-cyan-300',
        ],
        'pink' => [
            'background'   => 'bg-pink-300 dark:bg-pink-800',
            'textLight'    => 'text-pink-400 dark:text-pink-500',
            'text'         => 'text-pink-800 dark:text-pink-100',
            'textHover'    => 'hover:text-pink-950 dark:hover:text-pink-50',
            'outline'      => 'outline-pink-800 dark:outline-pink-400',
            'outlineLight' => 'outline-pink-800/50 dark:outline-pink-400/50',
            'outlineHover' => 'hover:outline-pink-600 dark:hover:outline-pink-300',
        ],
        'green' => [
            'background'   => 'bg-green-200 dark:bg-green-800',
            'textLight'    => 'text-green-400 dark:text-green-500',
            'text'         => 'text-green-800 dark:text-green-100',
            'textHover'    => 'hover:text-green-950 dark:hover:text-green-50',
            'outline'      => 'outline-green-700 dark:outline-green-400',
            'outlineLight' => 'outline-green-700/50 dark:outline-green-400/50',
            'outlineHover' => 'hover:outline-green-600 dark:hover:outline-green-300',
        ],
        'teal' => [
            'background'   => 'bg-teal-200 dark:bg-teal-800',
            'text'         => 'text-teal-800 dark:text-teal-100',
            'textLight'    => 'bg-teal-400 dark:text-teal-500',
            'textHover'    => 'hover:text-teal-950 dark:hover:text-teal-50',
            'outline'      => 'outline-teal-700 dark:outline-teal-400',
            'outlineLight' => 'outline-teal-700/50 dark:outline-teal-400/50',
            'outlineHover' => 'hover:outline-teal-600 dark:hover:outline-teal-300',
        ],
        'yellow' => [
            'background'   => 'bg-yellow-200 dark:bg-yellow-800',
            'text'         => 'text-yellow-800 dark:text-yellow-100',
            'textLight'    => 'text-yellow-400 dark:text-yellow-500',
            'textHover'    => 'hover:text-yellow-950 dark:hover:text-yellow-50',
            'outline'      => 'outline-yellow-700 dark:outline-yellow-400',
            'outlineLight' => 'outline-yellow-700/50 dark:outline-yellow-400/50',
            'outlineHover' => 'hover:outline-yellow-600 dark:hover:outline-yellow-300',
        ],
        'orange' => [
            'background'   => 'bg-orange-200 dark:bg-orange-800',
            'text'         => 'text-orange-800 dark:text-orange-100',
            'textLight'    => 'text-orange-400 dark:text-orange-500',
            'textHover'    => 'hover:text-orange-900 dark:hover:text-orange-50',
            'outline'      => 'outline-orange-700 dark:outline-orange-400',
            'outlineLight' => 'outline-orange-700/50 dark:outline-orange-400/50',
            'outlineHover' => 'hover:outline-orange-600 dark:hover:outline-orange-300',
        ],
        'purple' => [
            'background'   => 'bg-purple-200 dark:bg-purple-800',
            'text'         => 'text-purple-800 dark:text-purple-100',
            'textLight'    => 'text-purple-400 dark:text-purple-500',
            'textHover'    => 'hover:text-purple-950 dark:hover:text-purple-50',
            'outline'      => 'outline-purple-700 dark:outline-purple-400',
            'outlineLight' => 'outline-purple-700/50 dark:outline-purple-400/50',
            'outlineHover' => 'hover:outline-purple-600 dark:hover:outline-purple-300',
        ],
        'red' => [
            'background'   => 'bg-red-200 dark:bg-red-800',
            'text'         => 'text-red-800 dark:text-red-100',
            'textLight'    => 'text-red-400 dark:text-red-500',
            'textHover'    => 'hover:text-red-950 dark:hover:text-red-50',
            'outline'      => 'outline-red-700 dark:outline-red-400',
            'outlineLight' => 'outline-red-700/50 dark:outline-red-400/50',
            'outlineHover' => 'hover:outline-red-600 dark:hover:outline-red-300',
        ],
    ];

    public function getPalette($color = null)
    {
        if (substr($color, 0, 1) == '#') {
            $border = $this->adjustHexColor($color, -0.15);
            $text = $this->adjustHexColor($color, -0.7);
            return [
                'style'     => "background-color: {$color}; outline-color: {$border}; color: {$text};",
                'bgStyle'   => "background-color: {$color};",
                'textStyle' => "color: {$text};",
            ];
        }
        return $this->colors[$color] ?? $this->colors['gray'];
    }

    public function adjustHexColor($hexCode, $adjustPercent) {
        $hexCode = ltrim($hexCode, '#');
    
        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }
    
        $hexCode = array_map('hexdec', str_split($hexCode, 2));
    
        foreach ($hexCode as & $color) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount = ceil($adjustableLimit * $adjustPercent);
    
            $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
        }
    
        return '#' . implode($hexCode);
    }

    public function getHexContrastColor($hexcolor) {
        // Remove '#' if present
        $hexcolor = str_replace('#', '', $hexcolor);
    
        // Get RGB components
        $r = hexdec(substr($hexcolor, 0, 2));
        $g = hexdec(substr($hexcolor, 2, 2));
        $b = hexdec(substr($hexcolor, 4, 2));
    
        // Calculate YIQ value (perceived brightness)
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    
        // Return black or white based on YIQ threshold
        return ($yiq >= 128) ? 'black' : 'white';
    }
}
