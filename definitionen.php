<?php
defined( 'ABSPATH' ) or die( 'Kein Direktzugriff möglich!' );

$version='0.2.1';
define('DATEIPFAD','../export/');
define('HEADER1','<?xml version="1.0" encoding="UTF-8"?>'."\n".
	"<ORDER_LIST>\n"
);
define('HEADER2',"\t".'<ORDER xmlns="http://www.opentrans.org/XMLSchema/1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0" type="standard">'."\n".
	"\t\t<ORDER_HEADER>\n".
	"\t\t\t<CONTROL_INFO>\n".
	"\t\t\t\t<GENERATOR_INFO>Ad libitum Bestellexport manuell $version</GENERATOR_INFO>\n".
	"\t\t\t\t<GENERATION_DATE>".date(DATE_ATOM)."</GENERATION_DATE>\n".
	"\t\t\t</CONTROL_INFO>\n"
);
define('ORDER',"\t".'<ORDER xmlns="http://www.opentrans.org/XMLSchema/1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0" type="standard">'."\n");
define('GENERATORINFO',"\t\t\t\t<GENERATOR_INFO>Ad libitum WooCommerce-Bestellexport für Lexware manuell $version</GENERATOR_INFO>\n");

/* das Array brauch ich, um nicht EU-Ländern das Kennzeichen "Non-EU" zuweisen zu können */
$EU_Laender=array(
	'BE',
	'BG',
	'DK',
	'DE',
	'EE',
	'FI',
	'FR',
	'GR',
	'IE',
	'IT',
	'HR',
	'LV',
	'LT',
	'LU',
	'MT',
	'NL',
	'AT',
	'PL',
	'PT',
	'RO',
	'SE',
	'SK',
	'SI',
	'ES',
	'CZ',
	'HU',
	'GB',
	'CY'
);
?>