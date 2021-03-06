<?php
/* Copyright (C) 2012	Christophe Battarel	<christophe.battarel@altairis.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/margin/agentMargins.php
 *	\ingroup    margin
 *	\brief      Page des marges par agent commercial
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/margin/lib/margins.lib.php';

$langs->load("companies");
$langs->load("bills");
$langs->load("products");
$langs->load("margins");

// Security check
$agentid = GETPOST('agentid','int');

$mesg = '';

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="s.nom";
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$startdate=$enddate='';

if (!empty($_POST['startdatemonth']))
  $startdate  = date('Y-m-d', dol_mktime(12, 0, 0, $_POST['startdatemonth'],  $_POST['startdateday'],  $_POST['startdateyear']));
if (!empty($_POST['enddatemonth']))
  $enddate  = date('Y-m-d', dol_mktime(12, 0, 0, $_POST['enddatemonth'],  $_POST['enddateday'],  $_POST['enddateyear']));

/*
 * View
 */

$userstatic = new User($db);
$companystatic = new Societe($db);
$invoicestatic=new Facture($db);

$form = new Form($db);

llxHeader('',$langs->trans("Margins").' - '.$langs->trans("Agents"));

$text=$langs->trans("Margins");
print_fiche_titre($text);

// Show tabs
$head=marges_prepare_head($user);
$titre=$langs->trans("Margins");
$picto='margin';
dol_fiche_head($head, 'agentMargins', $titre, 0, $picto);

print '<form method="post" name="sel">';
print '<table class="border" width="100%">';

print '<tr><td width="20%">'.$langs->trans('CommercialAgent').'</td>';
print '<td colspan="4">';
print $form->select_dolusers($agentid,'agentid',1);
print '</td></tr>';

// Start date
print '<td>'.$langs->trans('StartDate').'</td>';
print '<td width="20%">';
$form->select_date($startdate,'startdate','','',1,"sel",1,1);
print '</td>';
print '<td width="20%">'.$langs->trans('EndDate').'</td>';
print '<td width="20%">';
$form->select_date($enddate,'enddate','','',1,"sel",1,1);
print '</td>';
print '<td style="text-align: center;">';
print '<input type="submit" class="button" value="'.$langs->trans('Launch').'" />';
print '</td></tr>';

// Total Margin
print '<tr style="font-weight: bold"><td>'.$langs->trans("TotalMargin").'</td><td colspan="4">';
print '<span id="totalMargin"></span>'; // set by jquery (see below)
print '</td></tr>';

// Margin Rate
if (! empty($conf->global->DISPLAY_MARGIN_RATES)) {
	print '<tr style="font-weight: bold"><td>'.$langs->trans("MarginRate").'</td><td colspan="4">';
	print '<span id="marginRate"></span>'; // set by jquery (see below)
	print '</td></tr>';
}

// Mark Rate
if (! empty($conf->global->DISPLAY_MARK_RATES)) {
	print '<tr style="font-weight: bold"><td>'.$langs->trans("MarkRate").'</td><td colspan="4">';
	print '<span id="markRate"></span>'; // set by jquery (see below)
	print '</td></tr>';
}

print "</table>";
print '</form>';

$sql = "SELECT s.nom, s.rowid as socid, s.code_client, s.client, sc.fk_user as agent,";
$sql.= " u.login,";
$sql.= " sum(d.subprice * d.qty * (1 - d.remise_percent / 100)) as selling_price,";
$sql.= " sum(d.buy_price_ht * d.qty) as buying_price, sum(((d.subprice * (1 - d.remise_percent / 100)) - d.buy_price_ht) * d.qty) as marge" ;
$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql.= ", ".MAIN_DB_PREFIX."facture as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact e ON e.element_id = f.rowid and e.statut = 4 and e.fk_c_type_contact = ".(empty($conf->global->AGENT_CONTACT_TYPE)?-1:$conf->global->AGENT_CONTACT_TYPE);
$sql.= ", ".MAIN_DB_PREFIX."facturedet as d";
$sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= ", ".MAIN_DB_PREFIX."user as u";
$sql.= " WHERE f.fk_soc = s.rowid";
$sql.= " AND sc.fk_soc = f.fk_soc";
if (! empty($conf->global->AGENT_CONTACT_TYPE))
	$sql.= " AND ((e.fk_socpeople IS NULL AND sc.fk_user = u.rowid) OR (e.fk_socpeople IS NOT NULL AND e.fk_socpeople = u.rowid))";
else
	$sql .= " AND sc.fk_user = u.rowid";
$sql.= " AND f.fk_statut > 0";
$sql.= " AND s.entity = ".$conf->entity;
$sql.= " AND d.fk_facture = f.rowid";
if ($agentid > 0) {
	if (! empty($conf->global->AGENT_CONTACT_TYPE))
  		$sql.= " AND ((e.fk_socpeople IS NULL AND sc.fk_user = ".$agentid.") OR (e.fk_socpeople IS NOT NULL AND e.fk_socpeople = ".$agentid."))";
	else
	    $sql .= " AND sc.fk_user = ".$agentid;
}
if (!empty($startdate))
  $sql.= " AND f.datef >= '".$startdate."'";
if (!empty($enddate))
  $sql.= " AND f.datef <= '".$enddate."'";
$sql .= " AND d.buy_price_ht IS NOT NULL";
if (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1)
	$sql .= " AND d.buy_price_ht <> 0";
if ($agentid > 0)
  $sql.= " GROUP BY s.rowid";
else
  $sql.= " GROUP BY u.rowid";
$sql.= " ORDER BY $sortfield $sortorder ";
$sql.= $db->plimit($conf->liste_limit +1, $offset);

$result = $db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);

	print '<br>';
	print_barre_liste($langs->trans("MarginDetails"),$page,$_SERVER["PHP_SELF"],"",$sortfield,$sortorder,'',$num,0,'');

	$i = 0;
	print "<table class=\"noborder\" width=\"100%\">";

	print '<tr class="liste_titre">';
	if ($agentid > 0)
		print_liste_field_titre($langs->trans("Customer"),$_SERVER["PHP_SELF"],"s.nom","","&amp;agentid=".$agentid,'align="center"',$sortfield,$sortorder);
	else
		print_liste_field_titre($langs->trans("CommercialAgent"),$_SERVER["PHP_SELF"],"u.login","","&amp;agentid=".$agentid,'align="center"',$sortfield,$sortorder);

	print_liste_field_titre($langs->trans("SellingPrice"),$_SERVER["PHP_SELF"],"selling_price","","&amp;agentid=".$agentid,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("BuyingPrice"),$_SERVER["PHP_SELF"],"buying_price","","&amp;agentid=".$agentid,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Margin"),$_SERVER["PHP_SELF"],"marge","","&amp;agentid=".$agentid,'align="right"',$sortfield,$sortorder);
	if (! empty($conf->global->DISPLAY_MARGIN_RATES))
		print_liste_field_titre($langs->trans("MarginRate"),$_SERVER["PHP_SELF"],"","","&amp;agentid=".$agentid,'align="right"',$sortfield,$sortorder);
	if (! empty($conf->global->DISPLAY_MARK_RATES))
		print_liste_field_titre($langs->trans("MarkRate"),$_SERVER["PHP_SELF"],"","","&amp;agentid=".$agentid,'align="right"',$sortfield,$sortorder);
	print "</tr>\n";

	$cumul_achat = 0;
	$cumul_vente = 0;
	$cumul_qty = 0;
	$rounding = min($conf->global->MAIN_MAX_DECIMALS_UNIT,$conf->global->MAIN_MAX_DECIMALS_TOT);

	if ($num > 0)
	{
		$var=true;
		while ($i < $num && $i < $conf->liste_limit)
		{
			$objp = $db->fetch_object($result);

			$marginRate = ($objp->buying_price != 0)?(100 * round($objp->marge / $objp->buying_price, 5)):'';
			$markRate = ($objp->selling_price != 0)?(100 * round($objp->marge / $objp->selling_price, 5)):'';

			$var=!$var;

			print "<tr $bc[$var]>";
			if ($agentid > 0) {
				$companystatic->id=$objp->socid;
				$companystatic->nom=$objp->nom;
				$companystatic->client=$objp->client;
				print "<td>".$companystatic->getNomUrl(1,'customer')."</td>\n";
			}
			else {
				$userstatic->id=$objp->agent;
				$userstatic->login=$objp->login;
				print "<td>".$userstatic->getLoginUrl(1)."</td>\n";
			}
			print "<td align=\"right\">".price($objp->selling_price)."</td>\n";
			print "<td align=\"right\">".price($objp->buying_price)."</td>\n";
			print "<td align=\"right\">".price($objp->marge)."</td>\n";
			if (! empty($conf->global->DISPLAY_MARGIN_RATES))
				print "<td align=\"right\">".(($marginRate === '')?'n/a':price($marginRate)."%")."</td>\n";
			if (! empty($conf->global->DISPLAY_MARK_RATES))
				print "<td align=\"right\">".(($markRate === '')?'n/a':price($markRate)."%")."</td>\n";
			print "</tr>\n";

			$i++;

			$cumul_achat += round($objp->buying_price, $rounding);
			$cumul_vente += round($objp->selling_price, $rounding);
		}
	}

	// affichage totaux marges
	$var=!$var;
	$totalMargin = $cumul_vente - $cumul_achat;
	$marginRate = ($cumul_achat != 0)?(100 * round($totalMargin / $cumul_achat, 5)):'';
	$markRate = ($cumul_vente != 0)?(100 * round($totalMargin / $cumul_vente, 5)):'';
	print '<tr '.$bc[$var].' style="border-top: 1px solid #ccc; font-weight: bold">';
	print '<td>';
	print $langs->trans('Total');
	print "</td>";
	print "<td align=\"right\">".price($cumul_vente)."</td>\n";
	print "<td align=\"right\">".price($cumul_achat)."</td>\n";
	print "<td align=\"right\">".price($totalMargin)."</td>\n";
	if (! empty($conf->global->DISPLAY_MARGIN_RATES))
		print "<td align=\"right\">".(($marginRate === '')?'n/a':price($marginRate)."%")."</td>\n";
	if (! empty($conf->global->DISPLAY_MARK_RATES))
		print "<td align=\"right\">".(($markRate === '')?'n/a':price($markRate)."%")."</td>\n";
	print "</tr>\n";

	print "</table>";
}
else
{
	dol_print_error($db);
}
$db->free($result);


llxFooter();
$db->close();
?>
<script type="text/javascript">
$(document).ready(function() {

  $("#agentid").change(function() {
     $("div.fiche form").submit();
  });

	$("#totalMargin").html("<?php echo price($totalMargin); ?>");
	$("#marginRate").html("<?php echo (($marginRate === '')?'n/a':price($marginRate)."%"); ?>");
	$("#markRate").html("<?php echo (($markRate === '')?'n/a':price($markRate)."%"); ?>");

});
</script>