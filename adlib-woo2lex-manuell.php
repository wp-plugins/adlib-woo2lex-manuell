<?php
/**
 * Plugin Name: WooCommerce-Bestellexport für Lexware
 * Plugin URI: www.ad-libitum.info/plugins/adlib-bestellexport-manuell/
 * Description: Exportiert Bestellungen manuell auf Knopfdruck
 * Text Domain: adlib-woo2lex-manuell
 * Domain Path: /lang
 * Version: 0.2.1
 * Author: Oliver Wagner
 * Author URI: http://www.ad-libitum.info
 * License: GPLv2 or later
 */
 
 /*
Copyright (C) 2015 Oliver Wagner

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/
 
defined( 'ABSPATH' ) or die( 'Kein Direktzugriff möglich!' );

register_activation_hook(__FILE__,'adl_erweitereDatenbank');
add_action( 'admin_menu','adlib_erstelleMenu');
add_action('init', 'load_plugin_language');	/*plugins_loaded*/

/**
 * Erweitere Datenbank
 *
 * Wenn WooCommerce installiert ist, dann und nur dann:
 * Erweitere die posts-Tabelle um das Feld 'exportiert' damit ich kennzeichnen kann,
 * welche Datensätze schon exportiert worden sind und welche nicht.
 *
 * @since  0.1
 * @change
 */
function adl_erweitereDatenbank() {
	/* gibt es woocommerce überhaupt? Wenn nicht, dann gleich Schluß machen */
	if (!class_exists('WooCommerce')) die("WooCommerce is not loaded. The plugin for the export of data can not be activated!");
	require_once('definitionen.php');
	global $wpdb;

	/* prüfen, ob es die Spalte adl_exportiert schon gibt */
	$sqlBefehl="SHOW COLUMNS FROM {$wpdb->prefix}posts LIKE 'adl_exportiert';";
	$zeilen=$wpdb->query($sqlBefehl);
	if ($zeilen==0) {
		/* Tabelle _posts um das erforderliche Feld erweitern */
		$sqlBefehl="ALTER TABLE {$wpdb->prefix}posts ADD adl_exportiert BOOLEAN NOT NULL DEFAULT FALSE;";
		$wpdb->query($sqlBefehl);
	}
	/* Exportverzeichnis anlegen, wenn es nicht schon existiert */
	$pfad=substr(DATEIPFAD,0,strlen(DATEIPFAD)-1);		/* ohne den / am Ende */
	if (!is_dir($pfad)) {
		mkdir ($pfad);
	}
}

/**
 * Menü erstellen
 *
 * @since  0.1
 * @change
 */
function adlib_erstelleMenu() {
	 add_menu_page(__('Datenexport', 'adlib-woo2lex-manuell'),__('Datenexport', 'adlib-woo2lex-manuell'),'export','adlib_export_manuell','fuellePluginmenu',plugin_dir_url( __FILE__ ).'export.png','81.10001');
 }
 
/**
 * Pluginmenü mit Infos füllen
 *
 * @since  0.1
 * @change
 */
function fuellePluginmenu() {
	?>
	<div class="wrap">
		<h2><?php esc_html_e('Manueller Datenexport', 'adlib-woo2lex-manuell') ?></h2>
		<p><?php _e('Pluginmenu_Absatz1', 'adlib-woo2lex-manuell') ?><a href="https://support.lexware.de/support/produkte/warenwirtschaft-pro/fragen-und-antworten/000000000047110?\" target="_blank\">Lexware</a>.</p>
		<p><?php _e('Pluginmenu_Absatz2', 'adlib-woo2lex-manuell') ?></p>
		<p><?php _e('Pluginmenu_Absatz3', 'adlib-woo2lex-manuell') ?></p>
		<p><?php _e('Pluginmenu_Absatz4', 'adlib-woo2lex-manuell') ?></p>
		<?php
			if ( isset( $_POST[ 'export' ] ) ) {
				$ergebnis=exportiereDaten();
				if ($ergebnis) {
		?>
			<div id="message" class="updated fade">
				<p><?php echo $ergebnis." "; printf(_n('Bestellung','Bestellungen',$ergebnis,'adlib-woo2lex-manuell')); echo " "; esc_html_e('Export', 'adlib-woo2lex-manuell') ?></p>
			</div>
		<?php
				} else {
		?>
			<div id="message" class="error fade">
				<p><?php esc_html_e('keine neuen Bestellungen', 'adlib-woo2lex-manuell') ?></p>
			</div>
		<?php
				}
		}
		?>
		<form method="post" action="">
			<input name="export" type="submit" id="export" value="<?php esc_html_e('Exportbutton', 'adlib-woo2lex-manuell') ?>" />
			<span class="description"><?php esc_html_e('Beispielexportdatei', 'adlib-woo2lex-manuell') ?></span>
		</form>
		<p style="width:50%;background-color:#c9c9c9;padding:10px;"><?php esc_html_e('Bettel1', 'adlib-woo2lex-manuell') ?> <img alt="PayPal" src="<?php echo plugin_dir_url( __FILE__ ); ?>paypal.png"></img> <?php esc_html_e('Bettel2', 'adlib-woo2lex-manuell') ?> <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=owagner@verizon-press.de&item_name=WooCommerce2Lexware&currency_code=EUR" target="_blank"><?php esc_html_e('Bettel3', 'adlib-woo2lex-manuell') ?></a>. <?php esc_html_e('Bettel4', 'adlib-woo2lex-manuell') ?> <a href="mailto:owagner@ad-libitum.info&subject=Woo2Lex">eMail</a> <?php esc_html_e('Bettel5', 'adlib-woo2lex-manuell') ?></p>
	</div>
	<?php
 }

/**
 * Exportiere Daten
 *
 * Exportiert die Bestelldaten von WooCommerce im Format für Lexware (openTRANS)
 *
 * @since  0.1
 * @change
 */
function exportiereDaten() {
	require_once('definitionen.php');
	global $wpdb;
	global $adlib_bestelldaten;
	
	/* diese Daten liefert WooCommerce nicht */
	$adlib_bestelldaten['liefer_fon']="";
	$adlib_bestelldaten['liefer_fax']="";
	$adlib_bestelldaten['liefer_email']="";
	$adlib_bestelldaten['rechnung_fax']="";
	
	/* alle noch nicht exportierten Bestellungen raussuchen */
	$abfrage="SELECT ID, post_date, post_excerpt FROM `".$wpdb->prefix."posts` WHERE post_type='shop_order' AND adl_exportiert=0 ORDER BY ID";
	$ergebnis=$wpdb->get_results($abfrage);
/*	debug_view("Anzahl",$wpdb->num_rows);*/
	$anzahlBestellungen=$wpdb->num_rows;
	if ($anzahlBestellungen==0) {
		return false;
	}
/*	debug_view("abfrage",$abfrage);*/
/*	debug_view("ergebnis",$ergebnis);*/
	/* Dateiname erzeugen, Datei anlegen und öffnen */
	$dateiname=DATEIPFAD.date('Ymd-His').".xml";
	$datei=fopen($dateiname,"wb");
	fwrite($datei,HEADER1);
	/*jede Bestellung anschauen */
	foreach($ergebnis as $wert) {
/*		debug_view("wert",$wert);*/
		$bestellID=$wert->ID;
/*		debug_view("bestellID",$bestellID);*/
		$adlib_bestelldaten['bestellnummer']=$bestellID;
		$adlib_bestelldaten['bestelldatum']=strftime('%Y-%m-%dT%H:%M:%S+01:00',strtotime($wert->post_date));
		$adlib_bestelldaten['remark_order']=ersetzeSonderzeichen($wert->post_excerpt);
		$abfrage="SELECT meta_key, meta_value FROM ".$wpdb->prefix."postmeta WHERE post_id=".$bestellID;
		$ergebnisPostMeta=$wpdb->get_results($abfrage);		/* jetzt haben wir die Kundendaten */
/*		debug_view("ergebnisPostMeta",$ergebnisPostMeta);*/
		foreach($ergebnisPostMeta as $wertPost) {
/*			debug_view("wertPost",$wertPost);*/
			switch ($wertPost->meta_key) {
				case "_order_currency":
					$adlib_bestelldaten['waehrung']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_country":
					$adlib_bestelldaten['rechnung_land']=ersetzeSonderzeichen($wertPost->meta_value);
					
					if (in_array($wertPost->meta_value,$EU_Laender)) {
						$adlib_bestelldaten['remark_tax_area']="Merchant";
					} else {
						$adlib_bestelldaten['remark_tax_area']="Non_EU";
					}
					break;
				case "_billing_first_name":
					$adlib_bestelldaten['rechnung_vorname']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_last_name":
					$adlib_bestelldaten['rechnung_nachname']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_company":
					$adlib_bestelldaten['rechnung_firma']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_address_1":
					$adlib_bestelldaten['rechnung_strasse']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_city":
					$adlib_bestelldaten['rechnung_ort']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_postcode":
					$adlib_bestelldaten['rechnung_plz']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_email":
					$adlib_bestelldaten['rechnung_email']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_billing_phone":
					$adlib_bestelldaten['rechnung_fon']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_shipping_country":
					$adlib_bestelldaten['liefer_land']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
					case "_shipping_first_name":
					$adlib_bestelldaten['liefer_vorname']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_shipping_last_name":
					$adlib_bestelldaten['liefer_nachname']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_shipping_company":
					$adlib_bestelldaten['liefer_firma']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_shipping_address_1":
					$adlib_bestelldaten['liefer_strasse']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_shipping_city":
					$adlib_bestelldaten['liefer_ort']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
				case "_shipping_postcode":
					$adlib_bestelldaten['liefer_plz']=ersetzeSonderzeichen($wertPost->meta_value);
					break;
			}
		}
		$adlib_bestelldaten['zahlung_zahlart']=56;
		$abfrage="SELECT order_item_name,order_item_type,order_item_id FROM ".$wpdb->prefix."woocommerce_order_items WHERE order_id=".$bestellID;
		$ergebnisArtikelliste=$wpdb->get_results($abfrage);		/* jetzt haben wir die Artikelliste */
		$schleifenzaehler=$wpdb->num_rows;
/*		debug_view("#Datensätze",$wpdb->num_rows);*/
/*		debug_view("Inhalt",$ergebnisArtikelliste);*/
		$artikelzaehler=-1;
/*		debug_view("ergebnisArtikelliste",$ergebnisArtikelliste);*/
		foreach($ergebnisArtikelliste as $wertArtikelliste) {
			if ($wertArtikelliste->order_item_type=='line_item') {
/*			debug_view("Trenner---------------",$dummy);*/
/*			debug_view("Artikeltyp",$ergebnisArtikelliste[$i]->order_item_type);*/
				$artikelzaehler+=1;
/*				debug_view("artikelzaehler",$artikelzaehler);*/
/*				debug_view("Schleife",$i);*/
/*				debug_view("ergebnisArtikelliste",$ergebnisArtikelliste[$i]->order_item_type);*/
				$artikelnummer=$wertArtikelliste->order_item_id;
				$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_bezeichnung_kurz']=ersetzeSonderzeichen($wertArtikelliste->order_item_name);
				$abfrage="SELECT * FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE order_item_id=".$artikelnummer;
				$ergebnisArtikel=$wpdb->get_results($abfrage);		/* jetzt haben wir die Artikeldaten */
				foreach ($ergebnisArtikel as $wertArtikeldaten) {
/*					debug_view("wertArtikeldaten",$wertArtikeldaten);*/
					switch ($wertArtikeldaten->meta_key) {
						case "_qty":
							$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_anzahl']=$wertArtikeldaten->meta_value;
							$menge=$wertArtikeldaten->meta_value;
							break;
						case "_product_id":
							$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_nummer']=$wertArtikeldaten->meta_value;
							$abfrage="SELECT post_content FROM ".$wpdb->prefix."posts WHERE ID=".$wertArtikeldaten->meta_value;
							$ergebnisBeschreibung=$wpdb->get_results($abfrage);		/* jetzt haben wir die lange Produktbeschreibung */
							$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_bezeichnung_lang']=ersetzeSonderzeichen($ergebnisBeschreibung[0]->post_content);
							break;
						case "_line_total":
							$preis=$wertArtikeldaten->meta_value;
							$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_zeilenpreis']=$wertArtikeldaten->meta_value;
							break;
						case "_line_tax":
							$steuer=$wertArtikeldaten->meta_value;
							break;
					}
				}
				$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_preis']=$preis/$menge;
				$adlib_bestelldaten['artikel'][$artikelzaehler]['artikel_steuersatz']=$steuer/$preis;
			}
			/* Frachtart und Frachtkosten */
			if ($wertArtikelliste->order_item_type=='shipping') {
				$adlib_bestelldaten['remark_delivery_method']=ersetzeSonderzeichen($wertArtikelliste->order_item_name);
				$frachtID=$wertArtikelliste->order_item_id;
				$abfrage="SELECT * FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE order_item_id=".$frachtID;
				$ergebnisFracht=$wpdb->get_results($abfrage);		/* jetzt haben wir die Frachtdaten */
				foreach ($ergebnisFracht as $wertFrachtdaten) {
					if ($wertFrachtdaten->meta_key=="cost") {
						$adlib_bestelldaten['remark_shipping_fee']=$wertFrachtdaten->meta_value;
					}
				}
			}
		}
/*		debug_view("adlib_bestelldaten",$adlib_bestelldaten);*/
/*		debug_view("ergebnisArtikelliste",$ergebnisArtikelliste);
		debug_view("artikelnummer",$artikelnummer);
		debug_view("ergebnisArtikel",$ergebnisArtikel);*/
		
		/* alle Daten ermittelt, jetzt in Datei schreiben */
		fwrite($datei,HEADER2);
		fwrite($datei,"\t\t\t<ORDER_INFO>\n");
		fwrite($datei,"\t\t\t\t<ORDER_ID>".$adlib_bestelldaten['bestellnummer']."</ORDER_ID>\n");
		fwrite($datei,"\t\t\t\t<ORDER_DATE>".$adlib_bestelldaten['bestelldatum']."</ORDER_DATE>\n");
		fwrite($datei,"\t\t\t\t<ORDER_PARTIES>\n");
		fwrite($datei,"\t\t\t\t\t<BUYER_PARTY>\n");
		fwrite($datei,"\t\t\t\t\t\t<PARTY>\n");
		fwrite($datei,"\t\t\t\t\t\t\t<ADDRESS>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<NAME>".$adlib_bestelldaten['liefer_firma']."</NAME>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<NAME2>".$adlib_bestelldaten['liefer_nachname']."</NAME2>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<NAME3>".$adlib_bestelldaten['liefer_vorname']."</NAME3>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<STREET>".$adlib_bestelldaten['liefer_strasse']."</STREET>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<ZIP>".$adlib_bestelldaten['liefer_plz']."</ZIP>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<CITY>".$adlib_bestelldaten['liefer_ort']."</CITY>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<COUNTRY>".$adlib_bestelldaten['liefer_land']."</COUNTRY>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<PHONE type=\"other\">".$adlib_bestelldaten['liefer_fon']."</PHONE>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<FAX>".$adlib_bestelldaten['liefer_fax']."</FAX>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<EMAIL>".$adlib_bestelldaten['liefer_email']."</EMAIL>\n");
		fwrite($datei,"\t\t\t\t\t\t\t</ADDRESS>\n");
		fwrite($datei,"\t\t\t\t\t\t</PARTY>\n");
		fwrite($datei,"\t\t\t\t\t</BUYER_PARTY>\n");
		fwrite($datei,"\t\t\t\t\t<INVOICE_PARTY>\n");
		fwrite($datei,"\t\t\t\t\t\t<PARTY>\n");
		fwrite($datei,"\t\t\t\t\t\t\t<ADDRESS>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<NAME>".$adlib_bestelldaten['rechnung_firma']."</NAME>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<NAME2>".$adlib_bestelldaten['rechnung_nachname']."</NAME2>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<NAME3>".$adlib_bestelldaten['rechnung_vorname']."</NAME3>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<STREET>".$adlib_bestelldaten['rechnung_strasse']."</STREET>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<ZIP>".$adlib_bestelldaten['rechnung_plz']."</ZIP>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<CITY>".$adlib_bestelldaten['rechnung_ort']."</CITY>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<COUNTRY>".$adlib_bestelldaten['rechnung_land']."</COUNTRY>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<PHONE type=\"other\">".$adlib_bestelldaten['rechnung_fon']."</PHONE>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<FAX>".$adlib_bestelldaten['rechnung_fax']."</FAX>\n");
		fwrite($datei,"\t\t\t\t\t\t\t\t<EMAIL>".$adlib_bestelldaten['rechnung_email']."</EMAIL>\n");
		fwrite($datei,"\t\t\t\t\t\t\t</ADDRESS>\n");
		fwrite($datei,"\t\t\t\t\t\t</PARTY>\n");
		fwrite($datei,"\t\t\t\t\t</INVOICE_PARTY>\n");
		fwrite($datei,"\t\t\t\t</ORDER_PARTIES>\n");
		fwrite($datei,"\t\t\t\t<PRICE_CURRENCY>".$adlib_bestelldaten['waehrung']."</PRICE_CURRENCY>\n");
		/* da fehlt noch der Payment-Block */

		fwrite($datei,"\t\t\t\t<REMARK type=\"delivery_method\">".$adlib_bestelldaten['remark_delivery_method']."</REMARK>\n");
		fwrite($datei,"\t\t\t\t<REMARK type=\"shipping_fee\">".$adlib_bestelldaten['remark_shipping_fee']."</REMARK>\n");
		fwrite($datei,"\t\t\t\t<REMARK type=\"tax_area\">".$adlib_bestelldaten['remark_tax_area']."</REMARK>\n");
		fwrite($datei,"\t\t\t\t<REMARK type=\"order\">".$adlib_bestelldaten['remark_order']."</REMARK>\n");
		/* additional costs gehen nicht, weil WooCommerce die Extrakosten auf die Frachtkosten draufrechnet */
		fwrite($datei,"\t\t\t</ORDER_INFO>\n");
		fwrite($datei,"\t\t</ORDER_HEADER>\n");
		fwrite($datei,"\t\t<ORDER_ITEM_LIST>\n");
		/*schleife */
		$gesamtpreis=0;
		for($i=0;$i<=$artikelzaehler;$i++) {
			fwrite($datei,"\t\t\t<ORDER_ITEM>\n");
			fwrite($datei,"\t\t\t\t<LINE_ITEM_ID>".$i."</LINE_ITEM_ID>\n");
			fwrite($datei,"\t\t\t\t<ARTICLE_ID>\n");
			fwrite($datei,"\t\t\t\t\t<SUPPLIER_AID>".$adlib_bestelldaten['artikel'][$i]['artikel_nummer']."</SUPPLIER_AID>\n");
			fwrite($datei,"\t\t\t\t\t<DESCRIPTION_SHORT>".$adlib_bestelldaten['artikel'][$i]['artikel_bezeichnung_kurz']."</DESCRIPTION_SHORT>\n");
			fwrite($datei,"\t\t\t\t\t<DESCRIPTION_LONG>".$adlib_bestelldaten['artikel'][$i]['artikel_bezeichnung_lang']."</DESCRIPTION_LONG>\n");
			fwrite($datei,"\t\t\t\t</ARTICLE_ID>\n");
			fwrite($datei,"\t\t\t\t<QUANTITY>".$adlib_bestelldaten['artikel'][$i]['artikel_anzahl']."</QUANTITY>\n");
			fwrite($datei,"\t\t\t\t<ORDER_UNIT>1</ORDER_UNIT>\n");
			fwrite($datei,"\t\t\t\t<ARTICLE_PRICE type=\"net_list\">\n");
			fwrite($datei,"\t\t\t\t\t<PRICE_AMOUNT>".number_format($adlib_bestelldaten['artikel'][$i]['artikel_preis'],2)."</PRICE_AMOUNT>\n");
			fwrite($datei,"\t\t\t\t\t<PRICE_LINE_AMOUNT>".number_format($adlib_bestelldaten['artikel'][$i]['artikel_zeilenpreis'],2)."</PRICE_LINE_AMOUNT>\n");
			$gesamtpreis+=$adlib_bestelldaten['artikel'][$i]['artikel_zeilenpreis'];
			fwrite($datei,"\t\t\t\t\t<TAX>".number_format($adlib_bestelldaten['artikel'][$i]['artikel_steuersatz'],2)."</TAX>\n");
			fwrite($datei,"\t\t\t\t</ARTICLE_PRICE>\n");
			fwrite($datei,"\t\t\t</ORDER_ITEM>\n");
		}
		/* schleife ende */
		fwrite($datei,"\t\t</ORDER_ITEM_LIST>\n");
		fwrite($datei,"\t\t<ORDER_SUMMARY>\n");
		fwrite($datei,"\t\t\t<TOTAL_ITEM_NUM>".($artikelzaehler+1)."</TOTAL_ITEM_NUM>\n");
		fwrite($datei,"\t\t\t<TOTAL_AMOUNT>".number_format($gesamtpreis,2)."</TOTAL_AMOUNT>\n");
		fwrite($datei,"\t\t</ORDER_SUMMARY>\n");
		fwrite($datei,"\t</ORDER>\n");
		/* exportierten Datensatz als exportiert kennzeichnen */
		$befehl="UPDATE ".$wpdb->prefix."posts SET adl_exportiert=1 WHERE ID=".$bestellID;
/*		debug_view("Befehl",$befehl);*/
		$wpdb->query($befehl);
		}
	/* offene Tags schließen und Daten ebenfalls schließen */
	fwrite($datei,"</ORDER_LIST>");
	fclose($datei);
	return $anzahlBestellungen;
}

/**
 * ersetze Sonderzeichen
 *
 * @since  0.1
 * @change
 */
function ersetzeSonderzeichen ($zeichenkette) {
	$zeichenkette=str_replace('€','EUR',$zeichenkette); 
/*	$zeichenkette=htmlentities ($zeichenkette, ENT_QUOTES, 'UTF-8');*/
	$zeichenkette=htmlspecialchars ($zeichenkette, ENT_QUOTES, 'UTF-8');
	$zeichenkette=str_replace('#039','apos',$zeichenkette); 		/* sonst funktionieren die einfachen Anführungszeichen nicht */
	$zeichenkette=str_replace('amp;','',$zeichenkette); 			/* sonst funktionieren die >< nicht */
/*	debug_view("zeichenkette",$zeichenkette);*/
	return $zeichenkette;
 }
 
/**
* Spracheinbindung
 *
 * @since   0.2
 * @change  0.2
 */

function load_plugin_language() {
	load_plugin_textdomain('adlib-woo2lex-manuell',	false, basename(dirname(__FILE__)).'/lang');
}

function debug_view ($name,$what) {
    echo "\n<pre>$name: ";
    if ( is_array( $what ) )  {
        print_r ( $what );
    } else {
        var_dump ( $what );
    }
    echo "</pre>\n";
}
 ?>