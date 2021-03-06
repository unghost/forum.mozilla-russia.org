<?php
/*************************************************************************************
 * c.php
 * -----
 * Author: Nigel McNie (oracle.shinoda@gmail.com)
 * Contributors:
 *  - Jack Lloyd (lloyd@randombit.net)
 * Copyright: (c) 2004 Nigel McNie (http://qbnz.com/highlighter/)
 * Release Version: 1.0.4
 * CVS Revision Version: $Revision: 1.1 $
 * Date Started: 2004/06/04
 * Last Modified: $Date: 2006/03/16 17:54:52 $
 *
 * C language file for GeSHi.
 *
 * CHANGES
 * -------
 * 2004/XX/XX (1.0.4)
 *   -  Added a couple of new keywords (Jack Lloyd)
 * 2004/11/27 (1.0.3)
 *   -  Added support for multiple object splitters
 * 2004/10/27 (1.0.2)
 *   -  Added support for URLs
 * 2004/08/05 (1.0.1)
 *   -  Added support for symbols
 * 2004/07/14 (1.0.0)
 *   -  First Release
 *
 * TODO (updated 2004/11/27)
 * -------------------------
 *  -  Get a list of inbuilt functions to add (and explore C more
 *     to complete this rather bare language file
 *
 *************************************************************************************
 *
 *     This file is part of GeSHi.
 *
 *   GeSHi is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   GeSHi is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with GeSHi; if not, write to the Free Software
 *   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 ************************************************************************************/

$language_data =  [
	'LANG_NAME' => 'C',
	'COMMENT_SINGLE' => [1 => '//', 2 => '#'],
	'COMMENT_MULTI' => ['/*' => '*/'],
	'CASE_KEYWORDS' => GESHI_CAPS_NO_CHANGE,
	'QUOTEMARKS' => ["'", '"'],
	'ESCAPE_CHAR' => '\\',
	'KEYWORDS' => [
		1 => [
			'if', 'return', 'while', 'case', 'continue', 'default',
			'do', 'else', 'for', 'switch', 'goto'
			],
		2 => [
			'null', 'false', 'break', 'true', 'function', 'enum', 'extern', 'inline'
			],
		3 => [
			'printf', 'cout'
			],
		4 => [
			'auto', 'char', 'const', 'double',  'float', 'int', 'long',
			'register', 'short', 'signed', 'sizeof', 'static', 'string', 'struct',
			'typedef', 'union', 'unsigned', 'void', 'volatile', 'wchar_t'
			],
		],
	'SYMBOLS' => [
		'(', ')', '{', '}', '[', ']', '=', '+', '-', '*', '/', '!', '%', '^', '&', ':'
		],
	'CASE_SENSITIVE' => [
		GESHI_COMMENTS => true,
		1 => false,
		2 => false,
		3 => false,
		4 => false,
		],
	'STYLES' => [
		'KEYWORDS' => [
			1 => 'color: #b1b100;',
			2 => 'color: #000000; font-weight: bold;',
			3 => 'color: #000066;',
			4 => 'color: #993333;'
			],
		'COMMENTS' => [
			1 => 'color: #808080; font-style: italic;',
			2 => 'color: #339933;',
			'MULTI' => 'color: #808080; font-style: italic;'
			],
		'ESCAPE_CHAR' => [
			0 => 'color: #000099; font-weight: bold;'
			],
		'BRACKETS' => [
			0 => 'color: #66cc66;'
			],
		'STRINGS' => [
			0 => 'color: #ff0000;'
			],
		'NUMBERS' => [
			0 => 'color: #cc66cc;'
			],
		'METHODS' => [
			1 => 'color: #202020;',
			2 => 'color: #202020;'
			],
		'SYMBOLS' => [
			0 => 'color: #66cc66;'
			],
		'REGEXPS' => [
			],
		'SCRIPT' => [
			]
		],
	'URLS' => [
		1 => '',
		2 => '',
		3 => 'http://www.opengroup.org/onlinepubs/009695399/functions/{FNAME}.html',
		4 => ''
		],
	'OOLANG' => true,
	'OBJECT_SPLITTERS' => [
		1 => '.',
		2 => '::'
		],
	'REGEXPS' => [
		],
	'STRICT_MODE_APPLIES' => GESHI_NEVER,
	'SCRIPT_DELIMITERS' => [
		],
	'HIGHLIGHT_STRICT_BLOCK' => [
		]
];

?>
