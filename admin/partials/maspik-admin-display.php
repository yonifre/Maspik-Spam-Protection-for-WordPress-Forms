<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Provide a admin area view for the plugin
 */

 // Forbidden languages
$MASPIK_FORBIDDEN_LANGUAGES = [
    // Unicode Scripts
    '\p{Arabic}' => esc_html__('Arabic', 'contact-forms-anti-spam'),
    '\p{Armenian}' => esc_html__('Armenian', 'contact-forms-anti-spam'),
    '\p{Bengali}' => esc_html__('Bengali', 'contact-forms-anti-spam'),
    '\p{Braille}' => esc_html__('Braille', 'contact-forms-anti-spam'),
    '\p{Ethiopic}' => esc_html__('Ethiopic', 'contact-forms-anti-spam'),
    '\p{Georgian}' => esc_html__('Georgian', 'contact-forms-anti-spam'),
    '\p{Greek}' => esc_html__('Greek', 'contact-forms-anti-spam'),
    '\p{Han}' => esc_html__('Han (Chinese)', 'contact-forms-anti-spam'),
    '\p{Katakana}' => esc_html__('Katakana', 'contact-forms-anti-spam'),
    '\p{Hiragana}' => esc_html__('Hiragana', 'contact-forms-anti-spam'),
    '\p{Hebrew}' => esc_html__('Hebrew', 'contact-forms-anti-spam'),
    '\p{Syriac}' => esc_html__('Syriac', 'contact-forms-anti-spam'),
    '\p{Latin}' => esc_html__('Latin', 'contact-forms-anti-spam'),
    '\p{Mongolian}' => esc_html__('Mongolian', 'contact-forms-anti-spam'),
    '\p{Thai}' => esc_html__('Thai', 'contact-forms-anti-spam'),
    '\p{Unknown}' => esc_html__('Unknown language', 'contact-forms-anti-spam'),

    // European Languages
    '[À-ÿ]' => esc_html__('French (À-ÿ)', 'contact-forms-anti-spam'),
    '[ÄäÖöÜüß]' => esc_html__('German ([ÄäÖöÜüß])', 'contact-forms-anti-spam'),
    '[ÉÍÓÚáéíóúüÜñÑ]' => esc_html__('Spanish (ÁÉÍÓÚáéíóúüÜñÑ)', 'contact-forms-anti-spam'),
    '[A-Za-z]' => esc_html__('English (A-Za-z)', 'contact-forms-anti-spam'),
    '[ÀàÉéÈèÌìÍíÒòÓóÙùÚú]' => esc_html__('Italian (ÀàÉéÈèÌìÍíÒòÓóÙùÚú)', 'contact-forms-anti-spam'),
    '[À-ÖØ-öø-ÿ]' => esc_html__('Dutch (À-ÖØ-öø-ÿ)', 'contact-forms-anti-spam'),
    '[ÄäÅåÖö]' => esc_html__('Swedish (ÄäÅåÖö)', 'contact-forms-anti-spam'),
    '[ÆæØøÅå]' => esc_html__('Danish (ÆæØøÅå)', 'contact-forms-anti-spam'),
    '[ÆæØøÅå]' => esc_html__('Norwegian (ÆæØøÅå)', 'contact-forms-anti-spam'),

    // Eastern European Languages
    '[А-Яа-яЁё]' => esc_html__('Russian (А-Яа-яЁ)', 'contact-forms-anti-spam'),
    '[ĄąĆćĘęŁłŃńÓóŚśŹźŻż]' => esc_html__('Polish (ĄąĆćĘęŁłŃńÓóŚśŹźŻż)', 'contact-forms-anti-spam'),
    '[ĂăÂâÎîȘșȚț]' => esc_html__('Romanian (ĂăÂâÎîȘșȚț)', 'contact-forms-anti-spam'),
    '[ÁáČčĎďÉéÍíĹĺĽľŇňÓóŔŕŠšŤťÚúÝýŽž]' => esc_html__('Czech (ÁáČčĎďÉéÍíĹĺĽľŇňÓóŔŕŠšŤťÚúÝýŽž)', 'contact-forms-anti-spam'),
    '[А-ЩЬЮЯЇІЄҐа-щьюяїієґЁё]' => esc_html__('Ukrainian (А-ЩЬЮЯЇІЄҐа-щьюяїієґЁё)', 'contact-forms-anti-spam'),
    '[ÁáÉéÍíÓóÚúÜüŐőŰű]' => esc_html__('Hungarian (ÁáÉéÍíÓóÚúÜüŐőŰ)', 'contact-forms-anti-spam'),
    '[ÁáÄäČčĎďÉéÍíĹĺĽľŇňÓóÔôŔŕŠšŤťÚúÝýŽž]' => esc_html__('Slovak (ÁáÄäČčĎďÉéÍíĹĺĽľŇňÓóÔôŔŕŠšŤťÚúÝýŽž)', 'contact-forms-anti-spam'),
    '[А-Яа-яЋћĆć]' => esc_html__('Serbian (А-Яа-яЋћĆć)', 'contact-forms-anti-spam'),

    // Other Languages
    '[ÇçĞğÖöŞşÜü]' => esc_html__('Turkish (ÇçĞğİıÖöŞşÜü)', 'contact-forms-anti-spam'),
];


// Required languages
$MASPIK_REQUIRED_LANGUAGES = [
    // Unicode Scripts
    '\p{Arabic}' => esc_html__('Arabic', 'contact-forms-anti-spam'),
    '\p{Armenian}' => esc_html__('Armenian', 'contact-forms-anti-spam'),
    '\p{Bengali}' => esc_html__('Bengali', 'contact-forms-anti-spam'),
    '\p{Braille}' => esc_html__('Braille', 'contact-forms-anti-spam'),
    '\p{Ethiopic}' => esc_html__('Ethiopic', 'contact-forms-anti-spam'),
    '\p{Georgian}' => esc_html__('Georgian', 'contact-forms-anti-spam'),
    '\p{Greek}' => esc_html__('Greek', 'contact-forms-anti-spam'),
    '\p{Han}' => esc_html__('Han (Chinese)', 'contact-forms-anti-spam'),
    '\p{Katakana}' => esc_html__('Katakana', 'contact-forms-anti-spam'),
    '\p{Hiragana}' => esc_html__('Hiragana', 'contact-forms-anti-spam'),
    '\p{Hebrew}' => esc_html__('Hebrew', 'contact-forms-anti-spam'),
    '\p{Syriac}' => esc_html__('Syriac', 'contact-forms-anti-spam'),
    '\p{Latin}' => esc_html__('Latin', 'contact-forms-anti-spam'),
    '\p{Mongolian}' => esc_html__('Mongolian', 'contact-forms-anti-spam'),
    '\p{Thai}' => esc_html__('Thai', 'contact-forms-anti-spam'),
    '\p{Unknown}' => esc_html__('Unknown language', 'contact-forms-anti-spam'),
    
    // European Languages
    '[A-Za-zÀ-ÿ]' => esc_html__('French (A-Za-zÀ-ÿ)', 'contact-forms-anti-spam'),
    '[A-Za-zÄäÖöÜüß]' => esc_html__('German ([A-Za-zÄäÖöÜüß])', 'contact-forms-anti-spam'),
    '[A-Za-zÁÉÍÓÚáéíóúüÜñÑ]' => esc_html__('Spanish (A-Za-zÁÉÍÓÚáéíóúüÜñÑ)', 'contact-forms-anti-spam'),
    '[A-Za-z]' => esc_html__('English (A-Za-z)', 'contact-forms-anti-spam'),
    '[A-Za-zÀàÉéÈèÌìÍíÒòÓóÙùÚú]' => esc_html__('Italian (A-Za-zÀàÉéÈèÌìÍíÒòÓóÙùÚú)', 'contact-forms-anti-spam'),
    '[A-Za-zÀ-ÖØ-öø-ÿ]' => esc_html__('Dutch (A-Za-zÀ-ÖØ-öø-ÿ)', 'contact-forms-anti-spam'),
    '[A-Za-zÄäÅåÖö]' => esc_html__('Swedish (A-Za-zÄäÅåÖö)', 'contact-forms-anti-spam'),
    '[A-Za-zÆæØøÅå]' => esc_html__('Danish (A-Za-zÆæØøÅå)', 'contact-forms-anti-spam'),
    '[A-Za-zÆæØøÅå]' => esc_html__('Norwegian (A-Za-zÆæØøÅå)', 'contact-forms-anti-spam'),
    
    // Eastern European Languages
    '[А-Яа-яЁё]' => esc_html__('Russian (А-Яа-яЁ)', 'contact-forms-anti-spam'),
    '[A-Za-zĄąĆćĘęŁłŃńÓóŚśŹźŻż]' => esc_html__('Polish (A-Za-zĄąĆćĘęŁłŃńÓóŚśŹźŻż)', 'contact-forms-anti-spam'),
    '[A-Za-zĂăÂâÎîȘșȚț]' => esc_html__('Romanian (A-Za-zĂăÂâÎîȘșȚț)', 'contact-forms-anti-spam'),
    '[A-Za-zÁáČčĎďÉéÍíĹĺĽľŇňÓóŔŕŠšŤťÚúÝýŽž]' => esc_html__('Czech (A-Za-zÁáČčĎďÉéÍíĹĺĽľŇňÓóŔŕŠšŤťÚúÝýŽž)', 'contact-forms-anti-spam'),
    '[A-Za-zА-ЩЬЮЯЇІЄҐа-щьюяїієґЁё]' => esc_html__('Ukrainian (A-Za-zА-ЩЬЮЯЇІЄҐа-щьюяїієґЁё)', 'contact-forms-anti-spam'),
    '[A-Za-zÁáÉéÍíÓóÚúÜüŐőŰű]' => esc_html__('Hungarian (A-Za-zÁáÉéÍíÓóÚúÜüŐőŰ)', 'contact-forms-anti-spam'),
    '[A-Za-zÁáÄäČčĎďÉéÍíĹĺĽľŇňÓóÔôŔŕŠšŤťÚúÝýŽž]' => esc_html__('Slovak (A-Za-zÁáÄäČčĎďÉéÍíĹĺĽľŇňÓóÔôŔŕŠšŤťÚúÝýŽž)', 'contact-forms-anti-spam'),
    '[A-Za-zА-Яа-яЋћĆć]' => esc_html__('Serbian (A-Za-zА-Яа-яЋћĆć)', 'contact-forms-anti-spam')
];


$MASPIK_COUNTRIES_LIST = [
    'Continent:AS' => 'Continent: Asia',
    'Continent:AF' => 'Continent: Africa', 
    'Continent:AN' => 'Continent: Antarctica',
    'Continent:EU' => 'Continent: Europe',
    'Continent:MA' => 'Continent: Americas',
    'Continent:OC' => 'Continent: Oceania',
    'AL' => 'Albania',
    'DZ' => 'Algeria',
    'AS' => 'American Samoa',
    'AD' => 'Andorra',
    'AO' => 'Angola',
    'AI' => 'Anguilla',
    'AG' => 'Antigua And Barbuda',
    'AR' => 'Argentina',
    'AM' => 'Armenia',
    'AW' => 'Aruba',
    'AU' => 'Australia',
    'AT' => 'Austria',
    'AZ' => 'Azerbaijan',
    'BS' => 'Bahamas',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'BB' => 'Barbados',
    'BY' => 'Belarus',
    'BE' => 'Belgium',
    'BZ' => 'Belize',
    'BJ' => 'Benin',
    'BM' => 'Bermuda',
    'BT' => 'Bhutan',
    'BO' => 'Bolivia',
    'BA' => 'Bosnia And Herzegovina',
    'BW' => 'Botswana',
    'BR' => 'Brazil',
    'IO' => 'British Indian Ocean Territory',
    'BN' => 'Brunei',
    'BG' => 'Bulgaria',
    'BF' => 'Burkina Faso',
    'BI' => 'Burundi',
    'KH' => 'Cambodia',
    'CM' => 'Cameroon',
    'CA' => 'Canada',
    'CV' => 'Cape Verde',
    'KY' => 'Cayman Islands',
    'CF' => 'Central African Republic',
    'TD' => 'Chad',
    'CL' => 'Chile',
    'CN' => 'China',
    'CO' => 'Colombia',
    'CG' => 'Congo',
    'CK' => 'Cook Islands',
    'CR' => 'Costa Rica',
    'CI' => 'Cote D\'ivoire',
    'HR' => 'Croatia',
    'CU' => 'Cuba',
    'CY' => 'Cyprus',
    'CZ' => 'Czech Republic',
    'CD' => 'Democratic Republic of the Congo',
    'DK' => 'Denmark',
    'DJ' => 'Djibouti',
    'DM' => 'Dominica',
    'DO' => 'Dominican Republic',
    'EC' => 'Ecuador',
    'EG' => 'Egypt',
    'SV' => 'El Salvador',
    'GQ' => 'Equatorial Guinea',
    'ER' => 'Eritrea',
    'EE' => 'Estonia',
    'ET' => 'Ethiopia',
    'FO' => 'Faroe Islands',
    'FM' => 'Federated States Of Micronesia',
    'FJ' => 'Fiji',
    'FI' => 'Finland',
    'FR' => 'France',
    'GF' => 'French Guiana',
    'PF' => 'French Polynesia',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GE' => 'Georgia',
    'DE' => 'Germany',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GR' => 'Greece',
    'GL' => 'Greenland',
    'GD' => 'Grenada',
    'GP' => 'Guadeloupe',
    'GU' => 'Guam',
    'GT' => 'Guatemala',
    'GN' => 'Guinea',
    'GW' => 'Guinea Bissau',
    'GY' => 'Guyana',
    'HT' => 'Haiti',
    'HN' => 'Honduras',
    'HK' => 'Hong Kong',
    'HU' => 'Hungary',
    'IS' => 'Iceland',
    'IN' => 'India',
    'ID' => 'Indonesia',
    'IR' => 'Iran',
    'IE' => 'Ireland',
    'IL' => 'Israel',
    'IM' => 'Isle of Man',
    'IT' => 'Italy',
    'JM' => 'Jamaica',
    'JP' => 'Japan',
    'JO' => 'Jordan',
    'KZ' => 'Kazakhstan',
    'KE' => 'Kenya',
    'KW' => 'Kuwait',
    'KG' => 'Kyrgyzstan',
    'LA' => 'Laos',
    'LV' => 'Latvia',
    'LB' => 'Lebanon',
    'LS' => 'Lesotho',
    'LY' => 'Libyan Arab Jamahiriya',
    'LI' => 'Liechtenstein',
    'LT' => 'Lithuania',
    'LU' => 'Luxembourg',
    'MK' => 'Macedonia',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'MY' => 'Malaysia',
    'MV' => 'Maldives',
    'ML' => 'Mali',
    'MT' => 'Malta',
    'MQ' => 'Martinique',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'MX' => 'Mexico',
    'MC' => 'Monaco',
    'MN' => 'Mongolia',
    'ME' => 'Montenegro',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'MM' => 'Myanmar',
    'NA' => 'Namibia',
    'NP' => 'Nepal',
    'NL' => 'Netherlands',
    'AN' => 'Netherlands Antilles',
    'NC' => 'New Caledonia',
    'NZ' => 'New Zealand',
    'NI' => 'Nicaragua',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'NF' => 'Norfolk Island',
    'MP' => 'Northern Mariana Islands',
    'NO' => 'Norway',
    'OM' => 'Oman',
    'PK' => 'Pakistan',
    'PW' => 'Palau',
    'PA' => 'Panama',
    'PG' => 'Papua New Guinea',
    'PY' => 'Paraguay',
    'PE' => 'Peru',
    'PH' => 'Philippines',
    'PL' => 'Poland',
    'PT' => 'Portugal',
    'PR' => 'Puerto Rico',
    'QA' => 'Qatar',
    'MD' => 'Republic Of Moldova',
    'RE' => 'Reunion',
    'RO' => 'Romania',
    'RU' => 'Russia',
    'RW' => 'Rwanda',
    'KN' => 'Saint Kitts And Nevis',
    'LC' => 'Saint Lucia',
    'VC' => 'Saint Vincent And The Grenadines',
    'WS' => 'Samoa',
    'SM' => 'San Marino',
    'ST' => 'Sao Tome And Principe',
    'SA' => 'Saudi Arabia',
    'SN' => 'Senegal',
    'RS' => 'Serbia',
    'SC' => 'Seychelles',
    'SG' => 'Singapore',
    'SK' => 'Slovakia',
    'SI' => 'Slovenia',
    'SB' => 'Solomon Islands',
    'ZA' => 'South Africa',
    'KR' => 'South Korea',
    'ES' => 'Spain',
    'LK' => 'Sri Lanka',
    'SD' => 'Sudan',
    'SR' => 'Suriname',
    'SZ' => 'Swaziland',
    'SE' => 'Sweden',
    'CH' => 'Switzerland',
    'SY' => 'Syrian Arab Republic',
    'TW' => 'Taiwan',
    'TJ' => 'Tajikistan',
    'TZ' => 'Tanzania',
    'TH' => 'Thailand',
    'TG' => 'Togo',
    'TO' => 'Tonga',
    'TT' => 'Trinidad And Tobago',
    'TN' => 'Tunisia',
    'TR' => 'Turkey',
    'TM' => 'Turkmenistan',
    'UG' => 'Uganda',
    'UA' => 'Ukraine',
    'AE' => 'United Arab Emirates',
    'GB' => 'United Kingdom',
    'US' => 'United States',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu',
    'VE' => 'Venezuela',
    'VN' => 'Vietnam',
    'VG' => 'Virgin Islands British',
    'VI' => 'Virgin Islands U.S.',
    'YE' => 'Yemen',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe'
];
        
// Countries list for phone
$MASPIK_COUNTRIES_LIST_FOR_PHONE = [
    'none' => 'None (User will enter the phone number with the country code)',
    'AL' => 'Albania (355)',
    'DZ' => 'Algeria (213)',
    'AS' => 'American Samoa (1684)',
    'AD' => 'Andorra (376)',
    'AO' => 'Angola (244)',
    'AI' => 'Anguilla (1264)',
    'AG' => 'Antigua And Barbuda (1268)',
    'AR' => 'Argentina (54)',
    'AM' => 'Armenia (374)',
    'AW' => 'Aruba (297)',
    'AU' => 'Australia (61)',
    'AT' => 'Austria (43)',
    'AZ' => 'Azerbaijan (994)',
    'BS' => 'Bahamas (1242)',
    'BH' => 'Bahrain (973)',
    'BD' => 'Bangladesh (880)',
    'BB' => 'Barbados (1246)',
    'BY' => 'Belarus (375)',
    'BE' => 'Belgium (32)',
    'BZ' => 'Belize (501)',
    'BJ' => 'Benin (229)',
    'BM' => 'Bermuda (1441)',
    'BT' => 'Bhutan (975)',
    'BO' => 'Bolivia (591)',
    'BA' => 'Bosnia And Herzegovina (387)',
    'BW' => 'Botswana (267)',
    'BR' => 'Brazil (55)',
    'IO' => 'British Indian Ocean Territory (246)',
    'BN' => 'Brunei (673)',
    'BG' => 'Bulgaria (359)',
    'BF' => 'Burkina Faso (226)',
    'BI' => 'Burundi (257)',
    'KH' => 'Cambodia (855)',
    'CM' => 'Cameroon (237)',
    'CA' => 'Canada (1)',
    'CV' => 'Cape Verde (238)',
    'KY' => 'Cayman Islands (1345)',
    'CF' => 'Central African Republic (236)',
    'TD' => 'Chad (235)',
    'CL' => 'Chile (56)',
    'CN' => 'China (86)',
    'CO' => 'Colombia (57)',
    'CG' => 'Congo (242)',
    'CK' => 'Cook Islands (682)',
    'CR' => 'Costa Rica (506)',
    'CI' => 'Cote D\'ivoire (225)',
    'HR' => 'Croatia (385)',
    'CU' => 'Cuba (53)',
    'CY' => 'Cyprus (357)',
    'CZ' => 'Czech Republic (420)',
    'CD' => 'Democratic Republic of the Congo (243)',
    'DK' => 'Denmark (45)',
    'DJ' => 'Djibouti (253)',
    'DM' => 'Dominica (1767)',
    'DO' => 'Dominican Republic (1809)',
    'EC' => 'Ecuador (593)',
    'EG' => 'Egypt (20)',
    'SV' => 'El Salvador (503)',
    'GQ' => 'Equatorial Guinea (240)',
    'ER' => 'Eritrea (291)',
    'EE' => 'Estonia (372)',
    'ET' => 'Ethiopia (251)',
    'FO' => 'Faroe Islands (298)',
    'FM' => 'Federated States Of Micronesia (691)',
    'FJ' => 'Fiji (679)',
    'FI' => 'Finland (358)',
    'FR' => 'France (33)',
    'GF' => 'French Guiana (594)',
    'PF' => 'French Polynesia (689)',
    'GA' => 'Gabon (241)',
    'GM' => 'Gambia (220)',
    'GE' => 'Georgia (995)',
    'DE' => 'Germany (49)',
    'GH' => 'Ghana (233)',
    'GI' => 'Gibraltar (350)',
    'GR' => 'Greece (30)',
    'GL' => 'Greenland (299)',
    'GD' => 'Grenada (1473)',
    'GP' => 'Guadeloupe (590)',
    'GU' => 'Guam (1671)',
    'GT' => 'Guatemala (502)',
    'GN' => 'Guinea (224)',
    'GW' => 'Guinea Bissau (245)',
    'GY' => 'Guyana (592)',
    'HT' => 'Haiti (509)',
    'HN' => 'Honduras (504)',
    'HK' => 'Hong Kong (852)',
    'HU' => 'Hungary (36)',
    'IS' => 'Iceland (354)',
    'IN' => 'India (91)',
    'ID' => 'Indonesia (62)',
    'IR' => 'Iran (98)',
    'IE' => 'Ireland (353)',
    'IL' => 'Israel (972)',
    'IM' => 'Isle of Man (44)',
    'IT' => 'Italy (39)',
    'JM' => 'Jamaica (1876)',
    'JP' => 'Japan (81)',
    'JO' => 'Jordan (962)',
    'KZ' => 'Kazakhstan (7)',
    'KE' => 'Kenya (254)',
    'KW' => 'Kuwait (965)',
    'KG' => 'Kyrgyzstan (996)',
    'LA' => 'Laos (856)',
    'LV' => 'Latvia (371)',
    'LB' => 'Lebanon (961)',
    'LS' => 'Lesotho (266)',
    'LY' => 'Libyan Arab Jamahiriya (218)',
    'LI' => 'Liechtenstein (423)',
    'LT' => 'Lithuania (370)',
    'LU' => 'Luxembourg (352)',
    'MK' => 'Macedonia (389)',
    'MG' => 'Madagascar (261)',
    'MW' => 'Malawi (265)',
    'MY' => 'Malaysia (60)',
    'MV' => 'Maldives (960)',
    'ML' => 'Mali (223)',
    'MT' => 'Malta (356)',
    'MQ' => 'Martinique (596)',
    'MR' => 'Mauritania (222)',
    'MU' => 'Mauritius (230)',
    'MX' => 'Mexico (52)',
    'MC' => 'Monaco (377)',
    'MN' => 'Mongolia (976)',
    'ME' => 'Montenegro (382)',
    'MA' => 'Morocco (212)',
    'MZ' => 'Mozambique (258)',
    'MM' => 'Myanmar (95)',
    'NA' => 'Namibia (264)',
    'NP' => 'Nepal (977)',
    'NL' => 'Netherlands (31)',
    'AN' => 'Netherlands Antilles (599)',
    'NC' => 'New Caledonia (687)',
    'NZ' => 'New Zealand (64)',
    'NI' => 'Nicaragua (505)',
    'NE' => 'Niger (227)',
    'NG' => 'Nigeria (234)',
    'NF' => 'Norfolk Island (672)',
    'MP' => 'Northern Mariana Islands (1670)',
    'NO' => 'Norway (47)',
    'OM' => 'Oman (968)',
    'PK' => 'Pakistan (92)',
    'PW' => 'Palau (680)',
    'PA' => 'Panama (507)',
    'PG' => 'Papua New Guinea (675)',
    'PY' => 'Paraguay (595)',
    'PE' => 'Peru (51)',
    'PH' => 'Philippines (63)',
    'PL' => 'Poland (48)',
    'PT' => 'Portugal (351)',
    'PR' => 'Puerto Rico (1787)',
    'QA' => 'Qatar (974)',
    'MD' => 'Republic Of Moldova (373)',
    'RE' => 'Reunion (262)',
    'RO' => 'Romania (40)',
    'RU' => 'Russia (7)',
    'RW' => 'Rwanda (250)',
    'KN' => 'Saint Kitts And Nevis (1869)',
    'LC' => 'Saint Lucia (1758)',
    'VC' => 'Saint Vincent And The Grenadines (1784)',
    'WS' => 'Samoa (685)',
    'SM' => 'San Marino (378)',
    'ST' => 'Sao Tome And Principe (239)',
    'SA' => 'Saudi Arabia (966)',
    'SN' => 'Senegal (221)',
    'RS' => 'Serbia (381)',
    'SC' => 'Seychelles (248)',
    'SG' => 'Singapore (65)',
    'SK' => 'Slovakia (421)',
    'SI' => 'Slovenia (386)',
    'SB' => 'Solomon Islands (677)',
    'ZA' => 'South Africa (27)',
    'KR' => 'South Korea (82)',
    'ES' => 'Spain (34)',
    'LK' => 'Sri Lanka (94)',
    'SD' => 'Sudan (249)',
    'SR' => 'Suriname (597)',
    'SZ' => 'Swaziland (268)',
    'SE' => 'Sweden (46)',
    'CH' => 'Switzerland (41)',
    'SY' => 'Syrian Arab Republic (963)',
    'TW' => 'Taiwan (886)',
    'TJ' => 'Tajikistan (992)',
    'TZ' => 'Tanzania (255)',
    'TH' => 'Thailand (66)',
    'TG' => 'Togo (228)',
    'TO' => 'Tonga (676)',
    'TT' => 'Trinidad And Tobago (1868)',
    'TN' => 'Tunisia (216)',
    'TR' => 'Turkey (90)',
    'TM' => 'Turkmenistan (993)',
    'UG' => 'Uganda (256)',
    'UA' => 'Ukraine (380)',
    'AE' => 'United Arab Emirates (971)',
    'GB' => 'United Kingdom (44)',
    'US' => 'United States (1)',
    'UY' => 'Uruguay (598)',
    'UZ' => 'Uzbekistan (998)',
    'VU' => 'Vanuatu (678)',
    'VE' => 'Venezuela (58)',
    'VN' => 'Vietnam (84)',
    'VG' => 'Virgin Islands British (1284)',
    'VI' => 'Virgin Islands U.S. (1340)',
    'YE' => 'Yemen (967)',
    'ZM' => 'Zambia (260)',
    'ZW' => 'Zimbabwe (263)'
];


global $MASPIK_FIELD_DISPLAY_NAMES;
global $MASPIK_TEMPLATES;


$spamcounter = maspik_spam_count();

$whats_new_version = function_exists('maspik_whats_new_version') ? maspik_whats_new_version() : '1';
$whats_new_seen = get_user_meta(get_current_user_id(), 'maspik_whats_new_seen_version', true);
$show_whats_new_auto = ($whats_new_seen !== $whats_new_version);
$whats_new_nonce = wp_create_nonce('maspik_whats_new_seen');
?>
<div class="wrap maspik-mainpage">
    <div class="maspik-settings">
        <!-- Header Section Start -->
        <div class="maspik-dashboard-header">
            <div class="maspik-branding">
                <div class="maspik-logo-section">
                    <h1 class="maspik-title">MASPIK</h1>
                    <?php 
                    $version_status = function_exists('maspik_check_version_status') ? maspik_check_version_status() : array('is_latest' => true, 'latest_version' => MASPIK_VERSION);
                    $version_class = $version_status['is_latest'] ? 'version-latest' : 'version-outdated';
                    ?>
                    <?php if( cfes_is_supporting() ): ?>
                        <h3 class="maspik-protag <?php echo esc_attr(maspik_add_pro_class("country_location")); ?>">Pro</h3>
                    <?php endif; ?>
                    <span class="maspik-version <?php echo esc_attr($version_class); ?>">
                        v<?php echo esc_html(MASPIK_VERSION); ?>
                        <?php if (!$version_status['is_latest']): ?>
                            <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="update-version" title="<?php echo esc_attr(sprintf(__('Update to version %s', 'contact-forms-anti-spam'), $version_status['latest_version'])); ?>">
                                <span class="dashicons dashicons-update"></span>
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php 
                if ( !cfes_is_supporting() ) {
                    echo "<div class='maspik-header-actions " . esc_attr(maspik_add_pro_class()) . "'>";
                    maspik_get_pro();
                    maspik_activate_license();
                    echo "</div>";
                }
                ?>
            </div>

            <div class="maspik-dashboard-overview">
                <h2><?php esc_html_e('Anti-Spam Protection Dashboard', 'contact-forms-anti-spam'); ?></h2>
                <div class="maspik-stats-cards">
                <div class="maspik-stat-card maspik-actions-card">
                        <div class="stat-content">
                            <h4><?php esc_html_e('Quick Actions', 'contact-forms-anti-spam'); ?></h4>
                            <div class="action-buttons">
                                <a href="https://wpmaspik.com/documentation/getting-started/" target="_blank" class="action-button action-button-guide">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <?php esc_html_e('Documentation', 'contact-forms-anti-spam'); ?>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=maspik-statistics')); ?>" class="action-button">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php esc_html_e('View Statistics', 'contact-forms-anti-spam'); ?>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=maspik-log.php')); ?>" class="action-button">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <?php esc_html_e('Spam Log', 'contact-forms-anti-spam'); ?>
                                </a>
                                <button type="button" class="action-button maspik-open-whats-new" id="maspik-open-whats-new-btn">
                                    <span class="dashicons dashicons-megaphone"></span>
                                    <?php esc_html_e("What's New?", 'contact-forms-anti-spam'); ?>
                                </button>
                                <?php if (!cfes_is_supporting("country_location")): ?>
                                <a href="https://wpmaspik.com/#pro?utm_source=plugin-dashboard" target="_blank" class="action-button action-button-upgrade">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php esc_html_e('Upgrade to Pro', 'contact-forms-anti-spam'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="maspik-stat-card">
                        <span class="dashicons dashicons-shield"></span>
                        <div class="stat-content">
                            <h4><?php esc_html_e('Total Spam Blocked', 'contact-forms-anti-spam'); ?></h4>
                            <p class="stat-number"><?php echo esc_html(number_format(get_option("spamcounter", 0))); ?></p>
                        </div>
                    </div>
                    <div class="maspik-stat-card">
                        <span class="dashicons dashicons-star-filled"></span>
                        <div class="stat-content">
                            <h4><?php esc_html_e('Last 7 Days Spam Blocked', 'contact-forms-anti-spam'); ?></h4>
                            <?php
                            global $wpdb;
                            $table = maspik_get_logtable();
                            $blocked_spam = 0;
                            
                            if (maspik_logtable_exists()) {
                                $blocked_spam = $wpdb->get_var(
                                    "SELECT COUNT(*) 
                                    FROM $table 
                                    WHERE spam_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                                );
                            }
                            ?>
                            <p class="stat-number"><?php echo number_format($blocked_spam); ?></p>
                        </div>
                    </div>
                </div>
                <style>
                    .maspik-actions-card {
                        padding: 20px !important;
                    }
                    .maspik-actions-card .stat-content {
                        width: 100%;
                    }
                    .maspik-actions-card h4 {
                        margin-bottom: 16px !important;
                    }
                    .action-buttons {
                        display: flex;
                        gap: 12px;
                        flex-wrap: wrap;
                    }
                    .action-button {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 8px 16px;
                        background: #f8f9fa;
                        border: 1px solid #e2e4e7;
                        border-radius: 8px;
                        color: #1d2327;
                        text-decoration: none;
                        font-size: 14px;
                        font-weight: 500;
                        transition: all 0.2s ease;
                    }
                    .action-button:hover {
                        background: #fff;
                        border-color: #F48722;
                        color: #F48722;
                        transform: translateY(-1px);
                    }
                    .action-button .dashicons {
                        font-size: 18px;
                        width: 18px;
                        height: 18px;
                        transition: color 0.2s ease;
                    }
                    .action-button:hover .dashicons {
                        color: #F48722;
                    }
                    .action-button-upgrade {
                        background: #F48722;
                        border-color: #F48722;
                        color: #fff;
                    }
                    .action-button-upgrade:hover {
                        background: #e06f0f;
                        border-color: #e06f0f;
                        color: #fff;
                    }
                    .action-button-upgrade .dashicons {
                        color: #fff;
                    }
                    .action-button-upgrade:hover .dashicons {
                        color: #fff;
                    }
                    .action-buttons .dashicons-star-filled {
                        color: #fff !important;
                    }
                </style>
            </div>

            <div class="maspik-protected-forms">
                <h3><?php esc_html_e('Protected Form Types', 'contact-forms-anti-spam'); ?></h3>
                <div class="forms-grid">
                    <?php
                    foreach (efas_array_supports_plugin() as $key => $value) {
                        if(maspik_if_plugin_is_active($key)){
                            $class = $value ? "pro" : "free";
                            $class .= efas_if_plugin_is_affective($key) ? ' enabled' : ' disabled';
                            if ($key == "Custom PHP Forms") {
                                $class .= " custom-php ";
                            }
                            $newvalue = $value ? " <span class='pro-badge'>($value)</span>" : "";
                            echo "<div class='form-type " . esc_attr($class) . " '>";
                            echo "<span class='form-icon dashicons dashicons-" . 
                                 (efas_if_plugin_is_affective($key) ? 'yes' : 'no') . "'></span>";
                            echo "<span class='form-name'>" . esc_html($key) . wp_kses_post($newvalue) . "</span>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <!-- Header Section End -->

        <div class="maspik-settings-body">
            <div class="maspik-blacklist-options">
                <!-- Rest of the existing content -->
                <?php
                settings_errors(); 
                $error_message = "";
                $save_notif = "";

                //Submit button command
                if(isset($_POST['maspik-save-btn']) || isset($_POST['maspik-api-save-btn'])) {
                    if (!isset($_POST['maspik_save_settings_nonce']) || !wp_verify_nonce($_POST['maspik_save_settings_nonce'], 'maspik_save_settings_action')) {
                        wp_die(esc_html__('Invalid nonce', 'contact-forms-anti-spam'), '', array('response' => 403));
                    }
                    maspik_save_command($error_message);
                    $save_notif = "yes";
                }
                //Submit button command - END

                //Save Commands
                    
                function maspik_save_command($error_message = ''){

                    //Check if the user has the permission to save the settings
                    if (!current_user_can('manage_options')) {
                        return;
                    }
                    
                    // Array of settings to save
                    $settings_to_save = [
                        'text_blacklist' => sanitize_textarea_field(stripslashes($_POST['text_blacklist'] ?? '')),
                        'text_limit_toggle' => isset($_POST['text_limit_toggle']) ? 1 : 0,
                        'MinCharactersInTextField' => sanitize_text_field($_POST['MinCharactersInTextField'] ?? ''),
                        'MaxCharactersInTextField' => sanitize_text_field($_POST['MaxCharactersInTextField'] ?? ''),
                        'text_custom_message_toggle' => isset($_POST['text_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_MaxCharactersInTextField' => sanitize_text_field(stripslashes($_POST['custom_error_message_MaxCharactersInTextField'] ?? '')),
                        'emails_blacklist' => sanitize_textarea_field(stripslashes($_POST['emails_blacklist'] ?? '')),
                        // 'textarea_blacklist' removed - merged into text_blacklist
                        'textarea_link_limit_toggle' => isset($_POST['textarea_link_limit_toggle']) ? 1 : 0,
                        'contain_links' => (isset($_POST['contain_links']) && $_POST['contain_links'] !== '') ? absint($_POST['contain_links']) : '',
                        'textarea_limit_toggle' => isset($_POST['textarea_limit_toggle']) ? 1 : 0,
                        'emoji_check' => isset($_POST['emoji_check']) ? 1 : 0,
                        'emoji_custom_message_toggle' => isset($_POST['emoji_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_emoji_check' => sanitize_text_field(stripslashes($_POST['custom_error_message_emoji_check'] ?? '')),
                        'MinCharactersInTextAreaField' => sanitize_text_field($_POST['MinCharactersInTextAreaField'] ?? ''),
                        'MaxCharactersInTextAreaField' => sanitize_text_field($_POST['MaxCharactersInTextAreaField'] ?? ''),
                        'textarea_custom_message_toggle' => isset($_POST['textarea_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_MaxCharactersInTextAreaField' => sanitize_text_field(stripslashes($_POST['custom_error_message_MaxCharactersInTextAreaField'] ?? '')),
                        'tel_formats' => sanitize_textarea_field(stripslashes($_POST['tel_formats'] ?? '')),
                        'tel_limit_toggle' => isset($_POST['tel_limit_toggle']) ? 1 : 0,
                        'MinCharactersInPhoneField' => sanitize_text_field($_POST['MinCharactersInPhoneField'] ?? ''),
                        'MaxCharactersInPhoneField' => sanitize_text_field($_POST['MaxCharactersInPhoneField'] ?? ''),
                        'phone_limit_custom_message_toggle' => isset($_POST['phone_limit_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_MaxCharactersInPhoneField' => sanitize_text_field(stripslashes($_POST['custom_error_message_MaxCharactersInPhoneField'] ?? '')),
                        'phone_custom_message_toggle' => isset($_POST['phone_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_tel_formats' => sanitize_text_field(stripslashes($_POST['custom_error_message_tel_formats'] ?? '')),
                        'lang_need_custom_message_toggle' => isset($_POST['lang_need_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_lang_needed' => sanitize_text_field(stripslashes($_POST['custom_error_message_lang_needed'] ?? '')),
                        'lang_forbidden_custom_message_toggle' => isset($_POST['lang_forbidden_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_lang_forbidden' => sanitize_text_field(stripslashes($_POST['custom_error_message_lang_forbidden'] ?? '')),
                        'AllowedOrBlockCountries' => sanitize_text_field($_POST['AllowedOrBlockCountries'] ?? 'block'),
                        'country_custom_message_toggle' => isset($_POST['country_custom_message_toggle']) ? 1 : 0,
                        'custom_error_message_country_blacklist' => sanitize_text_field(stripslashes($_POST['custom_error_message_country_blacklist'] ?? '')),
                        'private_file_id' => (isset($_POST['private_file_id']) && $_POST['private_file_id'] !== '') ? 
                            (absint($_POST['private_file_id']) > 2 ? absint($_POST['private_file_id']) : '') : '',
                        'popular_spam' => isset($_POST['popular_spam']) ? 1 : 0,
                        'maspikHoneypot' => isset($_POST['maspikHoneypot']) ? 1 : 0,
                        'maspikYearCheck' => isset($_POST['maspikYearCheck']) ? 1 : 0,
                        'maspikTimeCheck' => isset($_POST['maspikTimeCheck']) ? 1 : 0,
                        'NeedPageurl' => isset($_POST['NeedPageurl']) ? 1 : 0,
                        'ip_blacklist' => sanitize_textarea_field(stripslashes($_POST['ip_blacklist'] ?? '')),
                        'error_message' => sanitize_text_field(stripslashes($_POST['error_message'] ?? '')),
                        'abuseipdb_api' => sanitize_text_field(stripslashes($_POST['abuseipdb_api'] ?? '')),
                        'abuseipdb_score' => sanitize_text_field($_POST['abuseipdb_score'] ?? ''),
                        'proxycheck_io_api' => sanitize_text_field(stripslashes($_POST['proxycheck_io_api'] ?? '')),
                        'proxycheck_io_risk' => sanitize_text_field($_POST['proxycheck_io_risk'] ?? ''),
                        'numverify_api' => sanitize_text_field(stripslashes($_POST['numverify_api'] ?? '')),
                        'maspik_support_Elementor_forms' => sanitize_text_field(isset($_POST['maspik_support_Elementor_forms']) ? "yes" : "no"),
                        'maspik_support_cf7' => sanitize_text_field(isset($_POST['maspik_support_cf7']) ? "yes" : "no"),
                        'maspik_support_wp_comment' => sanitize_text_field(isset($_POST['maspik_support_wp_comment']) ? "yes" : "no"),
                        'maspik_support_registration' => sanitize_text_field(isset($_POST['maspik_support_registration']) ? "yes" : "no"),
                        'maspik_support_custom_forms' => sanitize_text_field(isset($_POST['maspik_support_custom_forms']) ? "yes" : "no"),
                        'maspik_support_woocommerce_review' => sanitize_text_field(isset($_POST['maspik_support_woocommerce_review']) ? "yes" : "no"),
                        'maspik_support_Woocommerce_registration' => sanitize_text_field(isset($_POST['maspik_support_Woocommerce_registration']) ? "yes" : "no"),
                        'maspik_support_woocommerce_orders' => sanitize_text_field(isset($_POST['maspik_support_woocommerce_orders']) ? "yes" : "no"),
                        'maspik_support_Wpforms' => sanitize_text_field(isset($_POST['maspik_support_Wpforms']) ? "yes" : "no"),
                        'maspik_support_formidable_forms' => sanitize_text_field(isset($_POST['maspik_support_formidable_forms']) ? "yes" : "no"),
                        'maspik_support_forminator_forms' => sanitize_text_field(isset($_POST['maspik_support_forminator_forms']) ? "yes" : "no"),
                        'maspik_support_fluentforms_forms' => sanitize_text_field(isset($_POST['maspik_support_fluentforms_forms']) ? "yes" : "no"),
                        'maspik_support_gravity_forms' => sanitize_text_field(isset($_POST['maspik_support_gravity_forms']) ? "yes" : "no"),
                        'maspik_support_bricks_forms' => sanitize_text_field(isset($_POST['maspik_support_bricks_forms']) ? "yes" : "no"),
                        'maspik_support_metform_forms' => sanitize_text_field(isset($_POST['maspik_support_metform_forms']) ? "yes" : "no"),
                        'maspik_support_bitform_forms' => sanitize_text_field(isset($_POST['maspik_support_bitform_forms']) ? "yes" : "no"),
                        'maspik_support_breakdance_forms' => sanitize_text_field(isset($_POST['maspik_support_breakdance_forms']) ? "yes" : "no"),
                        'maspik_support_ninjaforms' => sanitize_text_field(isset($_POST['maspik_support_ninjaforms']) ? "yes" : "no"),
                        'maspik_support_jetforms' => sanitize_text_field(isset($_POST['maspik_support_jetforms']) ? "yes" : "no"),
                        'maspik_support_everestforms' => sanitize_text_field(isset($_POST['maspik_support_everestforms']) ? "yes" : "no"),
                        'maspik_support_buddypress_forms' => sanitize_text_field(isset($_POST['maspik_support_buddypress_forms']) ? "yes" : "no"),
                        'maspik_support_helloplus_forms' => sanitize_text_field(isset($_POST['maspik_support_helloplus_forms']) ? "yes" : "no"),
                        'maspik_Store_log' => sanitize_text_field(isset($_POST['maspik_Store_log']) ? 'yes' : 'no'),
                        'spam_log_limit' => sanitize_text_field($_POST['spam_log_limit'] ?? ''),
                        'shere_data' => isset($_POST['shere_data']) ? 1 : 0,
                        'url_blacklist' => sanitize_textarea_field(stripslashes($_POST['url_blacklist'] ?? '')),
                        'maspik_ai_enabled' => isset($_POST['maspik_ai_enabled']) ? 1 : 0,
                        'maspik_ai_context' => sanitize_text_field(stripslashes($_POST['maspik_ai_context'] ?? '')),
                        'maspik_matrix_api_mode' => ( function () {
                            $m = isset( $_POST['maspik_matrix_api_mode'] ) ? absint( $_POST['maspik_matrix_api_mode'] ) : 4;
                            return in_array( $m, array( 2, 3, 4 ), true ) ? $m : 4;
                        } )(),
                    ]; 


                    // Save the settings
                    foreach ($settings_to_save as $key => $value) {
                        if (maspik_save_settings($key, $value) != "success") {
                            $error_message .= "Failed to save $key. ";
                        }
                    }

                    // WooCommerce Orders sub-settings: only when accordion was in the form (so we don't overwrite when feature is off)
                    if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && ! empty( $_POST['maspik_woo_orders_accordion_visible'] ) ) {
                        $woo_orders_error   = isset( $_POST['maspik_woo_orders_error_message'] ) ? sanitize_textarea_field( stripslashes( wp_unslash( $_POST['maspik_woo_orders_error_message'] ) ) ) : '';
                        $woo_orders_gateways = isset( $_POST['maspik_woo_orders_gateways_to_check'] ) && is_array( $_POST['maspik_woo_orders_gateways_to_check'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['maspik_woo_orders_gateways_to_check'] ) ) : array();
                        $woo_orders_zero    = isset( $_POST['maspik_woo_orders_check_zero_total'] ) ? 'yes' : 'no';
                        maspik_save_settings( 'maspik_woo_orders_error_message', $woo_orders_error );
                        maspik_save_settings( 'maspik_woo_orders_gateways_to_check', $woo_orders_gateways );
                        maspik_save_settings( 'maspik_woo_orders_check_zero_total', $woo_orders_zero );
                    }
                    
                    // Ensure AI client secret exists
                    if (isset($_POST['maspik_ai_enabled']) && $_POST['maspik_ai_enabled']) {
                        $existing_secret = maspik_get_settings('maspik_ai_client_secret');
                        if (empty($existing_secret) || $existing_secret === null) {
                            // Generate new client secret if it doesn't exist
                            if (function_exists('maspik_generate_ai_client_secret')) {
                                maspik_generate_ai_client_secret();
                            }
                        }
                    }
                    
                    // Save Options END --

                    // Array of select fields for processing
                    $select_fields = [
                        'lang_needed',
                        'numverify_country',
                        'country_blacklist',
                        'lang_forbidden'
                    ];

                    // Process and save select fields
                    foreach ($select_fields as $field_key ) {
                        $processedValues = '';
                        
                        if (isset($_POST[$field_key]) && !empty($_POST[$field_key])) {
                            $selectedValues = (array)$_POST[$field_key];
                            
                            foreach ($selectedValues as $value) {
                                // Sanitize the value
                                $escapedValue = sanitize_text_field($value);
                                $processedValues .= $escapedValue . " ";
                            }
                            $processedValues = trim(str_replace("\\p", "p", $processedValues));
                        }
                        
                        try {
                            if (maspik_save_settings($field_key, $processedValues) !== "success") {
                                $error_message .= sprintf(__('Failed to save %s settings. ', 'contact-forms-anti-spam'), $field_key);
                                error_log("Maspik: Failed to save {$field_key} settings");
                            }
                        } catch (Exception $e) {
                            $error_message .= sprintf(__('Error occurred while saving %s: %s ', 'contact-forms-anti-spam'), 
                                $field_key, 
                                $e->getMessage()
                            );
                            error_log("Maspik: Error saving {$field_key}: " . $e->getMessage());
                        }
                    }

                    
                }


                //Refresh Maspik API button Command

                if ( (isset( $_POST['maspik-api-refresh-btn'] ) || isset( $_POST['maspik-api-save-btn'] ) ) && cfes_is_supporting("api") ) {
                    
                    // Verify nonce
                    if (isset($_POST['maspik_save_settings_nonce']) && wp_verify_nonce($_POST['maspik_save_settings_nonce'], 'maspik_save_settings_action')) {
                        // Nonce is valid, proceed with refreshing API
                        cfas_refresh_api();
                        //$current_page = esc_url(admin_url("admin.php?page=maspik"));
                        // Redirect to avoid resubmission on page refresh
                        //echo "<script>window.location.replace('" . esc_js($current_page) . "');</script>";
                    } else {
                        // Nonce verification failed, handle accordingly
                        echo "<p>Error: Nonce verification failed.</p>";
                    }
                }

                //Refresh Maspik API button Command - END

                ?>   
                <div class="maspik-setting-body">
                    <div class="Xmaspik-blacklist-options">

                        <?php
                        if ( 'yes' === $save_notif ) {
                            global $wpdb;
                            if ( $error_message ) {
                                ?>
                        <div class="maspik-save-message-wrap" role="presentation">
                            <div class="maspik-save-toast maspik-save-toast--error" role="alert">
                                <span class="maspik-save-toast__icon dashicons dashicons-warning" aria-hidden="true"></span>
                                <span class="maspik-save-toast__text">
                                    <?php
                                    echo esc_html__( 'Error updating record:', 'contact-forms-anti-spam' );
                                    echo ' ';
                                    echo esc_html( $wpdb->last_error );
                                    ?>
                                </span>
                            </div>
                        </div>
                                <?php
                            } else {
                                ?>
                        <div class="maspik-save-message-wrap maspik-save-message-wrap--success" role="presentation">
                            <div class="maspik-save-toast maspik-save-toast--success" role="status" aria-live="polite">
                                <span class="maspik-save-toast__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                <span class="maspik-save-toast__text"><?php esc_html_e( 'Successfully Saved!', 'contact-forms-anti-spam' ); ?></span>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var wrap = document.querySelector('.maspik-save-message-wrap.maspik-save-message-wrap--success');
                            if (!wrap) return;
                            window.setTimeout(function () {
                                wrap.classList.add('maspik-save-message-wrap--leave');
                                window.setTimeout(function () { wrap.remove(); }, 400);
                            }, 2800);
                        })();
                        </script>
                                <?php
                            }
                        }
                        ?>


                        <!--accordions here-->
                        <div class="maspik-accordion">            
                            <form method="POST" action="" class="maspik-form">
                                <!--  Main check -->
                                <div class="main-spam-check togglewrap maspik-main-check--wrap maspik-accordion-content-wrap">
                                    <h3 class="maspik-header maspik-accordion-subtitle"><?php esc_html_e('Main Options', 'contact-forms-anti-spam'); ?></h3>
                                    <p><?php esc_html_e('Our recommendation: Take a few moments to browse through the settings, see what works best for your site, and customize your spam protection accordingly. Most features work automatically, but you can maximize protection by setting custom keywords.', 'contact-forms-anti-spam'); ?> <span style="color: #666; font-size: 0.95em;"><?php esc_html_e('Check your spam log from time to time to review blocked submissions and fine-tune your protection.', 'contact-forms-anti-spam'); ?></span></p>

                                    <!-- AI Spam Check Toggle - First in list -->
                                    <div class="maspik-ai-toggle-wrap togglewrap">
                                        <div class="maspik-toggle-and-chip">
                                            <?php echo maspik_toggle_button('maspik_ai_enabled', 'maspik_ai_enabled', 'maspik_ai_enabled', 'maspik-ai-toggle togglebutton', "ai-toggle", maspik_get_settings('maspik_ai_enabled')); ?>
                                            <?php
                                            $ai_effective = efas_get_spam_api('maspik_ai_enabled', 'bool');
                                            $ai_local = maspik_get_settings('maspik_ai_enabled');
                                            $ai_local_on = !empty($ai_local) && $ai_local !== '0' && $ai_local !== 0;
                                            $matrix_auto_enabled = get_option('maspik_matrix_auto_enabled', false);

                                            $matrix_mode_main_ui = maspik_get_settings( 'maspik_matrix_api_mode' );
                                            $matrix_mode_main_ui = ( $matrix_mode_main_ui === null || $matrix_mode_main_ui === '' ) ? 4 : absint( $matrix_mode_main_ui );
                                            if ( ! in_array( $matrix_mode_main_ui, array( 2, 3, 4 ), true ) ) {
                                                $matrix_mode_main_ui = 4;
                                            }
                                            $matrix_main_pill_suffix = ( 2 === $matrix_mode_main_ui ) ? '50' : ( ( 3 === $matrix_mode_main_ui ) ? '65' : '90' );
                                            $matrix_main_pill_title  = ( 2 === $matrix_mode_main_ui )
                                                ? esc_attr__( 'IP only — form content is not sent to Maspik.', 'contact-forms-anti-spam' )
                                                : ( ( 3 === $matrix_mode_main_ui )
                                                    ? esc_attr__( 'IP reputation plus banned-word checks on field text.', 'contact-forms-anti-spam' )
                                                    : esc_attr__( 'Full Matrix: heuristics, patterns, AI, and related checks.', 'contact-forms-anti-spam' ) );
                                            $matrix_main_mode_labels = array(
                                                2 => __( 'IP reputation only', 'contact-forms-anti-spam' ),
                                                3 => __( 'IP reputation + banned words', 'contact-forms-anti-spam' ),
                                                4 => __( 'Full Matrix analysis', 'contact-forms-anti-spam' ),
                                            );
                                            $matrix_main_mode_label = isset( $matrix_main_mode_labels[ $matrix_mode_main_ui ] ) ? $matrix_main_mode_labels[ $matrix_mode_main_ui ] : $matrix_main_mode_labels[4];
                                            
                                            if ($ai_effective && !$ai_local_on) {
                                                echo '<span class="maspik-dashboard-on-chip" title="' . esc_attr__('Active from dashboard', 'contact-forms-anti-spam') . '"><span class="dashicons dashicons-cloud" aria-hidden="true"></span><span class="maspik-dashboard-on-label">' . esc_html__('Active from dashboard', 'contact-forms-anti-spam') . '</span></span>';
                                            } elseif ($ai_local_on && $matrix_auto_enabled) {
                                                echo '<span class="maspik-dashboard-on-chip" style="background-color: #f0f6fc; color: #2271b1;" title="' . esc_attr__('Auto-enabled in version 2.7.0', 'contact-forms-anti-spam') . '"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span class="maspik-dashboard-on-label">' . esc_html__('Auto-enabled', 'contact-forms-anti-spam') . '</span></span>';
                                            }
                                            ?>
                                        </div>
                                        <div>
                                            <h4 class="maspik-matrix-main-heading">
                                                <span class="maspik-matrix-main-heading__title">
                                                <svg class="maspik-ai-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <rect class="ai-head" x="4" y="6" width="16" height="14" rx="2" fill="#F48722"/>
                                                    <circle class="ai-eye" cx="8" cy="12" r="1.5" fill="#ffffff"/>
                                                    <circle class="ai-eye" cx="16" cy="12" r="1.5" fill="#ffffff"/>
                                                    <rect class="ai-mouth" x="10" y="16" width="4" height="2" rx="1" fill="#ffffff"/>
                                                    <line class="ai-antenna" x1="12" y1="6" x2="12" y2="2" stroke="#F48722" stroke-width="2" stroke-linecap="round"/>
                                                    <circle class="ai-signal" cx="12" cy="2" r="1" fill="#F48722"/>
                                                </svg>
                                                <?php esc_html_e('MASPIK Matrix (Cloud-based protection)', 'contact-forms-anti-spam'); ?>
                                                </span>
                                                <span class="maspik-matrix-main-heading__mode" title="<?php echo esc_attr( $matrix_main_pill_title ); ?>">
                                                    <span class="screen-reader-text"><?php esc_html_e( 'Current Matrix mode:', 'contact-forms-anti-spam' ); ?></span>
                                                    <span class="maspik-matrix-mode-badge maspik-matrix-mode-badge--catch maspik-matrix-mode-badge--catch-<?php echo esc_attr( $matrix_main_pill_suffix ); ?> maspik-matrix-main-mode-pill"><?php printf( esc_html__( 'Mode: %s', 'contact-forms-anti-spam' ), esc_html( $matrix_main_mode_label ) ); ?></span>
                                                </span>
                                            </h4>
                                            <span>
                                                <?php esc_html_e('Multi-layer check: IP reputation, request patterns, and AI scoring.', 'contact-forms-anti-spam'); ?>
                                                <a href="#maspik-matrix-section" class="maspik-accordion-link" data-accordion-target="maspik-matrix-section" style="margin-<?php echo is_rtl() ? 'right' : 'left'; ?>: 6px; font-size: 0.9em;">
                                                    <?php esc_html_e('Choose Mode and see more options', 'contact-forms-anti-spam'); ?>
                                                </a>
                                            </span>
                                            <p class="maspik-matrix-privacy-note" style="margin-top: 0.5em; font-size: 0.9em; color: #646970;">
                                                <?php esc_html_e('This feature sends limited request data (Depends on the mode) to external servers for analysis. Data is processed in real-time and not stored.', 'contact-forms-anti-spam'); ?>
                                            </p>
                                            <?php
                                            // AI metrics summary under Matrix title (per month and total)
                                            $ai_metrics = maspik_get_settings( 'maspik_ai_metrics' );
                                            if ( is_array( $ai_metrics ) ) :
                                                $total_checks = isset( $ai_metrics['total_checks'] ) ? (int) $ai_metrics['total_checks'] : 0;
                                                $total_spam   = isset( $ai_metrics['total_spam'] ) ? (int) $ai_metrics['total_spam'] : 0;
                                                $month_key    = date( 'Ym' );
                                                $month_data   = isset( $ai_metrics['by_month'][ $month_key ] ) && is_array( $ai_metrics['by_month'][ $month_key ] )
                                                    ? $ai_metrics['by_month'][ $month_key ]
                                                    : array( 'checks' => 0, 'spam' => 0 );
                                                $month_checks = (int) ( $month_data['checks'] ?? 0 );
                                                $month_spam   = (int) ( $month_data['spam'] ?? 0 );
                                            ?>
                                            <p class="maspik-matrix-ai-metrics maspik-subtext" style="margin-top: 0.4em;">
                                                <?php
                                                if ( $month_checks > 0 ) {
                                                    /* translators: 1: number of AI checks this month, 2: number blocked as spam */
                                                    printf(
                                                        esc_html__( 'This month: %1$s MASPIK Matrix checks, %2$s blocked as spam.', 'contact-forms-anti-spam' ),
                                                        esc_html( number_format_i18n( $month_checks ) ),
                                                        esc_html( number_format_i18n( $month_spam ) )
                                                    );
                                                } elseif ( $total_checks > 0 ) {
                                                    /* translators: 1: total AI checks, 2: total blocked as spam */
                                                    printf(
                                                        esc_html__( 'Since activation: %1$s MASPIK Matrix checks, %2$s blocked as spam.', 'contact-forms-anti-spam' ),
                                                        esc_html( number_format_i18n( $total_checks ) ),
                                                        esc_html( number_format_i18n( $total_spam ) )
                                                    );
                                                } else {
                                                    esc_html_e( 'Matrix is ready. Data will appear here after your first MASPIK Matrix check.', 'contact-forms-anti-spam' );
                                                }
                                                ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php
                                    $ai_effective_for_notice = efas_get_spam_api('maspik_ai_enabled', 'bool');
                                    if ( ! $ai_effective_for_notice ) :
                                    ?>
                                    <div class="maspik-matrix-disabled-notice notice notice-warning" style="margin: 0.75em 0; padding: 0.75em 1em;">
                                        <span class="dashicons dashicons-warning" style="margin-right: 0.25em;" aria-hidden="true"></span>
                                        <strong><?php esc_html_e('Advanced spam protection is limited', 'contact-forms-anti-spam'); ?></strong>
                                        <?php esc_html_e('MASPIK Matrix is disabled. Some advanced detection features (including AI-based checks) will not work at full capacity, and protection may be less effective.', 'contact-forms-anti-spam'); ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="maspik-txt-custom-msg-head togglewrap maspik-honeypot-wrap">
                                        <div class="maspik-toggle-and-chip">
                                            <?php 
                                                echo maspik_toggle_button(
                                                    'maspikHoneypot', 
                                                    'maspikHoneypot', 
                                                    'maspikHoneypot', 
                                                    'maspik-honeypot togglebutton',
                                                    "",
                                                    ""
                                                ); 
                                            ?>
                                            <?php
                                            $hp_effective = efas_get_spam_api('maspikHoneypot', 'bool');
                                            $hp_local = maspik_get_settings('maspikHoneypot');
                                            $hp_local_on = !empty($hp_local) && $hp_local !== '0' && $hp_local !== 0;
                                            if ($hp_effective && !$hp_local_on) {
                                                echo '<span class="maspik-dashboard-on-chip" title="' . esc_attr__('Active from dashboard', 'contact-forms-anti-spam') . '"><span class="dashicons dashicons-cloud" aria-hidden="true"></span><span class="maspik-dashboard-on-label">' . esc_html__('Active from dashboard', 'contact-forms-anti-spam') . '</span></span>';
                                            }
                                            ?>
                                        </div>
                                        <div>
                                            <h4> <?php esc_html_e('Honeypot Trap', 'contact-forms-anti-spam'); ?></h4>
                                            <span><?php esc_html_e('Adds an invisible field to your form. Humans can\'t see it, but bots often fill it. If this hidden field has data, the submission is blocked as spam. This traps bots without affecting real users.', 'contact-forms-anti-spam'); ?></span>
                                        </div>  
                                    </div><!-- end of maspik-honeypot-wrap -->

                                    <?php if( efas_if_plugin_is_active('elementor-pro') ) {  ?>
                                        <div class="maspik-txt-custom-msg-head togglewrap maspik-block-inquiry-wrap">
                                            <div class="maspik-toggle-and-chip">
                                                <?php echo maspik_toggle_button('NeedPageurl', 'NeedPageurl', 'NeedPageurl', 'maspik-needpageurl togglebutton',"","",['NeedPageurl']); ?>
                                                <?php
                                                $np_effective = efas_get_spam_api('NeedPageurl', 'bool');
                                                $np_local = maspik_get_settings('NeedPageurl');
                                                $np_local_on = !empty($np_local) && $np_local !== '0' && $np_local !== 0;
                                                if ($np_effective && !$np_local_on) {
                                                    echo '<span class="maspik-dashboard-on-chip" title="' . esc_attr__('Active from dashboard', 'contact-forms-anti-spam') . '"><span class="dashicons dashicons-cloud" aria-hidden="true"></span><span class="maspik-dashboard-on-label">' . esc_html__('Active from dashboard', 'contact-forms-anti-spam') . '</span></span>';
                                                }
                                                ?>
                                            </div>
                                                <div>
                                                    <h4> <?php esc_html_e('Elementor Bot detector', 'contact-forms-anti-spam'); ?></h4>
                                                    <span><?php esc_html_e('Blocks form submissions that are sent from outside your site (e.g. bots sending POST requests from an external source).', 'contact-forms-anti-spam'); ?></span>
                                            </div>  
                                        </div><!-- end of maspik-block-inquiry-wrap -->
                                    <?php  } ?>

                                    <!-- Advance key check start -->
                                    <div class="maspik-txt-custom-msg-head togglewrap maspik-honeypot-wrap">
                                        <div class="maspik-toggle-and-chip">
                                            <?php echo maspik_toggle_button('maspikTimeCheck', 'maspikTimeCheck', 'maspikTimeCheck', 'maspik-honeypot togglebutton',"",""); ?>
                                            <?php
                                            $tc_effective = efas_get_spam_api('maspikTimeCheck', 'bool');
                                            $tc_local = maspik_get_settings('maspikTimeCheck');
                                            $tc_local_on = !empty($tc_local) && $tc_local !== '0' && $tc_local !== 0;
if ($tc_effective && !$tc_local_on) {
                                            echo '<span class="maspik-dashboard-on-chip" title="' . esc_attr__('Active from dashboard', 'contact-forms-anti-spam') . '"><span class="dashicons dashicons-cloud" aria-hidden="true"></span><span class="maspik-dashboard-on-label">' . esc_html__('Active from dashboard', 'contact-forms-anti-spam') . '</span></span>';
                                        }
                                            ?>
                                        </div>
                                        <div>
                                            <h4> <?php esc_html_e('Advance key check', 'contact-forms-anti-spam'); ?></h4>
                                            <span><?php esc_html_e('Adds a hidden field with a unique key that is generated when the form is loaded. Blocks submissions that have no key or a wrong key — e.g. forms sent from outside your site (direct POST), cached pages, or old copies of the form. Complements the Honeypot: the key blocks "no key" submissions; the honeypot blocks bots that fill the invisible field.', 'contact-forms-anti-spam'); ?></span>
                                            <br>
                                            <span><?php esc_html_e('Please clear cache after activating this feature.', 'contact-forms-anti-spam'); ?></span>
                                        </div>  
                                    </div><!-- end of Advance key check -->

                                    <?php maspik_save_button_show() ?>
                                </div>

                                <!-- Accordion Item - End main check -->
                                <!-- Accordion Item - Language Field - Custom -->

                                <?php $text_pro = "(Pro feature)"; 
                                $span_pro = !cfes_is_supporting("language_restrictions") ? ' <span style="color: #f48623;font-size: 12px;text-transform: none;">' . $text_pro . '</span>' : ''; ?>
                                <div class="maspik-accordion-item maspik-accordion-lang-field <?php echo esc_attr(maspik_add_pro_class("language_restrictions")) ?> ">
                                    <div class="maspik-accordion-header">
                                        <div class="mpk-acc-header-texts">
                                            <h4 class="maspik-header maspik-accordion-header-text"><span class="dashicons dashicons-star-filled"></span><?php esc_html_e('Language restrictions', 'contact-forms-anti-spam'); echo $span_pro; ?></h4><!--Accordion Title-->
                                            <span class="maspik-accordion-subheader"></span>
                                        </div>
                                        <div class ="maspik-pro-button-wrap">
                                            <?php maspik_get_pro() ?>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                        </div>
                                    </div>
                                        
                                    <div class="maspik-accordion-content">
                                        <div class="maspik-accordion-content-wrap hide-form-title">
                                            <div class="maspik-accordion-subtitle-wrap">
                                                <h3 class="maspik-accordion-subtitle"><?php esc_html_e('Language Required', 'contact-forms-anti-spam'); ?></h3>
                                                <?php 
                                                    maspik_tooltip("ONLY accepts form submissions containing at least one character in one of your selected languages.");
                                                ?>
                                            </div> <!--end of maspik-accordion-subtitle-wrap-->
                                                    
                                            <div class="maspik-main-list-wrap maspik-select-list">

                                                <?php 
                                                    echo create_maspik_select("lang_needed", "maspik-lang-need", $MASPIK_REQUIRED_LANGUAGES);                                 
                                                    maspik_spam_api_list('lang_needed', $MASPIK_REQUIRED_LANGUAGES);
                                                ?>    

                                            </div> <!-- end of maspik-main-list-wrap -->
                                            <span class="maspik-subtext">
                                                <span class="text-caution">
                                                    <?php esc_html_e('Caution:', 'contact-forms-anti-spam'); ?>
                                                </span>
                                            <?php esc_html_e('When specifying Latin-based languages (e.g., Dutch, French), the check includes language-specific punctuation and letters A to Z (including English). This is to avoid false positives when certain punctuation marks are not used.', 'contact-forms-anti-spam'); ?>
                                            </span>
                                                    
                                            <div class="maspik-custom-msg-wrap">
                                                <div class="maspik-txt-custom-msg-head togglewrap">
                                                    <?php echo maspik_toggle_button('lang_need_custom_message_toggle', 'lang_need_custom_message_toggle', 'lang_need_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",['custom_error_message_lang_needed']); ?>
                                                        
                                                    <h4> <?php esc_html_e('Custom validation error message', 'contact-forms-anti-spam'); ?> </h4>
                                                </div>

                                                <div class="maspik-custom-msg-box togglebox">
                                                    <?php echo create_maspik_textarea('custom_error_message_lang_needed', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                        
                                                </div>
                                                        
                                            </div><!-- end of maspik-custom-msg-wrap -->


                                            <!---- Language section divider S---------->
                                            <div class = 'maspik-simple-divider'></div>
                                            <!---- Language section divider E---------->



                                            <div class="maspik-accordion-subtitle-wrap">
                                                <h3 class="maspik-accordion-subtitle"><?php esc_html_e('Language Forbidden', 'contact-forms-anti-spam'); ?></h3>
                                                <?php 
                                                    maspik_tooltip("Select the languages you wish to block from filling out your forms.");
                                                ?>
                                            </div> <!--end of aspik-accordion-subtitle-wrap-->
                                                    
                                            <div class="maspik-main-list-wrap maspik-select-list">

                                                <?php 
                                                    echo create_maspik_select("lang_forbidden", "maspik-lang-forbidden", $MASPIK_FORBIDDEN_LANGUAGES);
                                                    maspik_spam_api_list('lang_forbidden', $MASPIK_FORBIDDEN_LANGUAGES);                           
                                                ?>      

                                            </div> <!-- end of maspik-main-list-wrap -->
                                            <span class="maspik-subtext"><?php 
                                                    esc_html_e('If there is even one character from one of these languages in the text fields, it will be marked as spam and blocked.', 'contact-forms-anti-spam'); 
                                                    echo "<br>";
                                                    echo "<span class='text-caution'>";
                                                    esc_html_e('Caution:', 'contact-forms-anti-spam');
                                                    echo " </span>";
                                                    esc_html_e('When blocking Latin languages in an individual (such as: Dutch, French), the check is in the punctuation letters (But they are not always in use). Its to prevent false positive.', 'contact-forms-anti-spam'); ?>
                                                    
                                            </span>
                                                    
                                            <div class="maspik-custom-msg-wrap">
                                                <div class="maspik-txt-custom-msg-head togglewrap">
                                                    <?php echo maspik_toggle_button('lang_forbidden_custom_message_toggle', 'lang_forbidden_custom_message_toggle', 'lang_forbidden_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",['custom_error_message_lang_forbidden']); ?>
                                                        
                                                    <h4> <?php esc_html_e('Custom validation error message', 'contact-forms-anti-spam'); ?> </h4>
                                                </div>

                                                <div class="maspik-custom-msg-box togglebox">
                                                    <?php echo create_maspik_textarea('custom_error_message_lang_forbidden', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                        
                                                </div>
                                                        
                                            </div><!-- end of maspik-custom-msg-wrap -->

                                            <?php maspik_save_button_show() ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Accordion Item - Country Field - Custom -->
                                <div class="maspik-accordion-item has-high-tooltip maspik-accordion-country-field <?php echo esc_attr(maspik_add_pro_class("country_location")) ?> ">
                                    <div class="maspik-accordion-header">
                                        <div class="mpk-acc-header-texts">
                                            <h4 class="maspik-header maspik-accordion-header-text">
                                                <span class="dashicons dashicons-star-filled"></span>
                                                <?php esc_html_e('Geolocation restrictions', 'contact-forms-anti-spam'); echo $span_pro; ?>
                                            </h4><!--Accordion Title-->
                                            <?php 
                                                    maspik_tooltip("Choose either to allow or to block and enter the countries in the next field.   
                                                    If allowed, only forms from these countries will be accepted.
                                                    If blocked, all countries in the following list will be blocked.");
                                            ?>
                                        </div>
                                        <div class ="maspik-pro-button-wrap">
                                            <?php maspik_get_pro() ?>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                        </div>
                                    </div>
                                        
                                    <div class="maspik-accordion-content">
                                        <div class="maspik-accordion-content-wrap hide-form-title">
                                                    
                                            <div class="maspik-select-list">
                                                <?php 
                                                $is_spi_stronger = efas_get_spam_api('country_blacklist') && 
                                                efas_get_spam_api('AllowedOrBlockCountries') && 
                                                efas_get_spam_api('AllowedOrBlockCountries',"string") != 'ignore';
                                            
                                                $attr = $is_spi_stronger ? "disabled='disabled'" : false;
                                                echo $is_spi_stronger ? "<span><b>Setting disabled and managed by Maspik deshbord</b></span>" : "";
                                                ?>
                                                <div class="maspik-main-list-wrap">
                                                    
                                                    <?php 
                                                        echo maspik_simple_dropdown('AllowedOrBlockCountries', 'maspik-country-dropdown' , 
                                                        array(
                                                            'Allow' => 'allow',
                                                            'Block' => 'block'

                                                        ),$attr);
                                                        echo create_maspik_select("country_blacklist", "country_blacklist", $MASPIK_COUNTRIES_LIST ,$attr);                                 
                                                    ?> 
                                                </div>
                                                    <?php
                                                    if($is_spi_stronger){ 
                                                        maspik_spam_api_list('AllowedOrBlockCountries'); 
                                                        maspik_spam_api_list('country_blacklist', $MASPIK_COUNTRIES_LIST);
                                                    }
                                                ?>
                                            </div> <!-- end of maspik-main-list-wrap -->
                                                    
                                            <div class="maspik-custom-msg-wrap">
                                                <div class="maspik-txt-custom-msg-head togglewrap">
                                                    <?php echo maspik_toggle_button('country_custom_message_toggle', 'country_custom_message_toggle', 'country_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",["custom_error_message_country_blacklist"]); ?>
                                                        
                                                    <h4> <?php esc_html_e('Custom validation error message', 'contact-forms-anti-spam'); ?> </h4>
                                                </div>

                                                <div class="maspik-custom-msg-box togglebox">
                                                    <?php echo create_maspik_textarea('custom_error_message_country_blacklist', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                        
                                                </div>
                                                        
                                            </div><!-- end of maspik-custom-msg-wrap -->
                                            <?php maspik_save_button_show() ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Accordion Item - Maspik API Field - Custom -->
                                <div class="maspik-accordion-item has-high-tooltip maspik-accordion-maspik-api-field <?php echo esc_attr(maspik_add_pro_class()) ?> ">
                                    <div class="maspik-accordion-header">
                                        <div class="mpk-acc-header-texts">
                                            <h4 class="maspik-header maspik-accordion-header-text">
                                                <span class="dashicons dashicons-star-filled"></span>
                                                <?php esc_html_e('MASPIK Dashboard', 'contact-forms-anti-spam'); echo $span_pro; ?>
                                            </h4><!--Accordion Title-->
                                            <?php 
                                                    maspik_tooltip("Every day, the API file downloads new data from the API server.
                                                    <em>If you would like to manually refresh now, just click the Reset API File button.</em>");
                                            ?>
                                        </div>
                                        <div class ="maspik-pro-button-wrap">
                                            <?php maspik_get_pro() ?>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                        </div>
                                    </div>
                                        
                                    <div class="maspik-accordion-content">
                                        <div class="maspik-accordion-content-wrap hide-form-title">
                                                                        
                                            <div class="maspik-popular-spam-wrap">
                                                <div class="maspik-txt-custom-msg-head togglewrap">
                                                    <?php echo maspik_toggle_button('popular_spam', 'popular_spam', 'popular_spam', 'maspik-toggle-custom-message'); ?>
                                                    <div>
                                                        <h4> <?php esc_html_e('Auto-populate spam phrases', 'contact-forms-anti-spam'); ?> </h4>
                                                        <span><?php esc_html_e('Popular spam words from', 'contact-forms-anti-spam'); ?> <a target = "_blank" href="https://wpmaspik.com/public-api/">
                                                            <?php esc_html_e('Maspik spam blacklist', 'contact-forms-anti-spam'); ?></a>
                                                            <br>
                                                            <?php esc_html_e('Risk level:', 'contact-forms-anti-spam'); ?>
                                                            <span class="maspik-risk-level">
                                                                <?php esc_html_e('Medium', 'contact-forms-anti-spam'); ?>
                                                            </span>
                                                        </span>
                                                    </div>   
                                                </div>
                                                
                                                        
                                            </div><!-- end of maspik-popular-spam-wrap -->
                                            
                                            <!---- Language section divider S---------->
                                            <div class = 'maspik-simple-divider'></div>
                                            <!---- Language section divider E---------->

                                            <div class="maspik-setting-info">
                                                <h4><?php esc_html_e('Maspik Dashboard ID', 'contact-forms-anti-spam'); ?></h4>
                                                <div class = 'maspik-status-wrap'>
                                                    
                                                    
                                                        <?php
                                                            $is_connected = check_maspik_api_values();
                                                        ?>
                                                        <span class="maspik-status-label"><?php esc_html_e('Status', 'contact-forms-anti-spam'); ?></span>
                                                        <span class="maspik-api-status <?php echo $is_connected ? 'connected' : 'not-connected'; ?>">
                                                            <span class="dashicons dashicons-cloud maspik-api-icon" aria-hidden="true"></span>
                                                            <span class="maspik-api-status-text">
                                                                <?php echo $is_connected ? esc_html__('Connected', 'contact-forms-anti-spam') : esc_html__('Not Connected', 'contact-forms-anti-spam'); ?>
                                                            </span>
                                                        </span>

                                                </div>

                                            </div> <!--end of maspik-setting-info-->
                                        <span><?php esc_html_e('Create your own single Dashboard for managing multiple websites, at', 'contact-forms-anti-spam'); ?> <a target = "_blank" href="https://wpmaspik.com/add-your-private-api/?inplugin">
                                            <?php esc_html_e('WpMaspik', 'contact-forms-anti-spam'); ?></a> <?php esc_html_e('website', 'contact-forms-anti-spam'); ?></span>

                                            <div class="maspik-main-list-wrap">
                                                <?php 
                                                    echo create_maspik_input('private_file_id', 'maspik-inputbox', 'number');                      
                                                ?> 
                                            </div> <!-- end of maspik-main-list-wrap -->

                                            <div class="maspik-api-buttons-warp">
                                            <?php 
                                                maspik_save_button_show('Refresh API file', 'maspik-api-refresh maspik-btn-outline','maspik-api-refresh-btn');


                                                maspik_save_button_show('Verify and Save', 'maspik-api-save','maspik-api-save-btn') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>




                                <div class="maspik-section-head maspik-more-setting">
                                    
                                    <h2 class='maspik-title maspik-bl-title'><?php esc_html_e('By Field Options', 'contact-forms-anti-spam'); ?></h2>
                                    <ul>
                                        <li><?php esc_html_e('Create a list of words or phrases you want to block.', 'contact-forms-anti-spam'); ?></li>
                                        <li><?php esc_html_e('Each term should be on a separate line.', 'contact-forms-anti-spam'); ?></li>
                                        <li><?php esc_html_e('The system is not case-sensitive', 'contact-forms-anti-spam'); ?></li>
                                    </ul>
                                    <p><?php esc_html_e('Learn more about those option in our documentation', 'contact-forms-anti-spam'); ?> <a target="_blank" href="https://wpmaspik.com/documentation/?fromplugin"><?php esc_html_e('here', 'contact-forms-anti-spam'); ?></a>.</p>


                                </div>

                                <!-- Accordion Item - Text Field - Custom -->
                                <div class="maspik-accordion-item maspik-accordion-text-field">
                                    <div class="maspik-accordion-header">
                                        <div class="mpk-acc-header-texts">
                                            <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e('Text Fields', 'contact-forms-anti-spam');?></h4><!--Accordion Title-->
                                            <span class="maspik-accordion-subheader">
                                                <?php esc_html_e('(Text inputs & text areas, e.g. Name, Subject, Message)', 'contact-forms-anti-spam');?></span>
                                        </div>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                    </div>
                                        
                                    <div class="maspik-accordion-content">
                                        <div class="maspik-accordion-content-wrap hide-form-title">
                                            <div class="maspik-setting-info">
                                                <?php 
                                                    maspik_tooltip("If the text or textarea value CONTAINS one of the given values, it will be marked as spam and blocked. This setting applies to both text fields and textarea fields.");
                                                        
                                                    maspik_popup("Eric jones|SEO|ranking|currency|click here", "Text field", "See examples" ,"visibility");
                                                ?>
                                            </div> <!--end of maspik-setting-info-->
                                                    
                                            <div class="maspik-main-list-wrap maspik-textfield-list">

                                                <label for="text_blacklist"><b><?php esc_html_e('Forbidden keywords (applies to text and textarea fields, one per line):', 'contact-forms-anti-spam'); ?></b></label>
                                                <?php
                                                    echo create_maspik_textarea('text_blacklist', 6, 80, 'maspik-textarea' , 'Seo&#10;Eric jones&#10;Crypto&#10;...');
                                                        
                                                    maspik_spam_api_list('text_field');
                                                ?>      

                                            </div> <!-- end of maspik-main-list-wrap -->
                                            <div class="maspik-subtext">
                                                <h5><?php esc_html_e('How to use block text input fields with this option?', 'contact-forms-anti-spam'); ?></h5>
                                                <ul class="methods-list maspik-list">
                                                    <li><?php esc_html_e('Enter the complete name (e.g: Eric jones)', 'contact-forms-anti-spam'); ?></li>
                                                    <li><?php esc_html_e('Enter specific word (e.g: Eric) to block all names that contain the word Eric, like Eric jones, but not Ericjones (without space)', 'contact-forms-anti-spam'); ?></li>
                                                    <li><?php esc_html_e('For advanced users - use wildcards (*) to create flexible blocking patterns', 'contact-forms-anti-spam'); ?></li>
                                                </ul>
                                            </div>
                                                    
                                                <!-- Limit Links (applies to both text and textarea fields) -->
                                                <div class="maspik-limit-char-wrap">
                                                    <div class="maspik-limit-char-head togglewrap">
                                                        <?php           
                                                        echo maspik_toggle_button('textarea_link_limit_toggle', 'textarea_link_limit_toggle', 'textarea_link_limit_toggle', 'maspik-toggle-text-limit togglebutton',"","",['contain_links']);
                                                        
                                                        echo "<h4>" . esc_html__('Limit Links', 'contact-forms-anti-spam') . "</h4>";

                                                        maspik_tooltip("Spammers tend to include links. If there is no reason for anyone to send links when completing your forms, set this to 0. This setting applies to both text and textarea fields.");
                                                        ?>
                                                    </div>

                                                    <div class="maspik-limit-char-box togglebox">
                                                        <?php echo create_maspik_numbox("text_limit_link", "contain_links", "link-limit" , "Max links allowed (Set 0 for not even one link)", "", "0") ?>
                                                        <span class="maspik-subtext">
                                                            <?php esc_html_e('This setting applies to both text and textarea fields.', 'contact-forms-anti-spam'); ?>
                                                        </span>
                                                    </div><!-- end of maspik-limit-link-wrap -->
                                                </div>

                                                <!-- Block if contains Emojis (applies to both text and textarea fields) -->
                                                <div class="maspik-limit-char-wrap">
                                                    <div class="maspik-limit-char-head togglewrap">
                                                        <?php        
                                                        echo maspik_toggle_button('emoji_check', 'emoji_check', 'emoji_check', 'maspik-toggle-emoji_check togglebutton',"","",['emoji_check']);
                                                        
                                                        echo "<h4>" . esc_html__('Block if contains Emojis', 'contact-forms-anti-spam') . "</h4>";
                                                        
                                                        maspik_tooltip("Spammers tend to include emojis. If there is no reason for anyone to send emojis when completing your forms, toggle this option on. This setting applies to both text and textarea fields.");
                                                        ?>
                                                    </div><!-- end of head -->
                                                    <div class="maspik-limit-char-box togglebox">
                                                        <div class="maspik-custom-msg-wrap">
                                                            <div class="maspik-txt-custom-msg-head togglewrap">
                                                                <?php echo maspik_toggle_button('emoji_custom_message_toggle', 'emoji_custom_message_toggle', 'emoji_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",['custom_error_message_emoji_check']); ?>
                                                                <h4><?php esc_html_e('Validation message to display when emojis are found', 'contact-forms-anti-spam'); ?></h4>
                                                            </div>
                                                            <div class="maspik-custom-msg-box togglebox">
                                                                <?php echo create_maspik_textarea('custom_error_message_emoji_check', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                            </div>
                                                        </div><!-- end of maspik-custom-msg-wrap -->
                                                        <span class="maspik-subtext">
                                                            <?php esc_html_e('This setting applies to both text and textarea fields.', 'contact-forms-anti-spam'); ?>
                                                        </span>
                                                    </div><!-- end of maspik-limit-char-box -->
                                                </div><!-- end of maspik-limit-char-wrap -->

                                                <!-- Character Limits Section -->
                                                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
                                                    <h3 style="margin-bottom: 20px; color: #333; font-size: 16px; font-weight: 600;">
                                                        <?php esc_html_e('Character Limits', 'contact-forms-anti-spam'); ?>
                                                    </h3>
                                                    <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                                                        <?php esc_html_e('Configure character limits separately for short text fields and textarea fields (long text).', 'contact-forms-anti-spam'); ?>
                                                    </p>

                                                    <!-- Limit Characters for Short Text Fields -->
                                                    <div class="maspik-limit-char-wrap" style="margin-bottom: 25px;">
                                                        <div class="maspik-limit-char-head togglewrap">
                                                            <?php
                                                                echo maspik_toggle_button('text_limit_toggle', 'text_limit_toggle', 'text_limit_toggle', 'maspik-toggle-text-limit togglebutton',"","",['MinCharactersInTextField','MaxCharactersInTextField','custom_error_message_MaxCharactersInTextField']);
                                                                        
                                                                echo "<h4>" . esc_html__('Limit Characters - Short Text Fields (Name/Subject)', 'contact-forms-anti-spam') . "</h4>";

                                                                maspik_tooltip("If the short text field (name, subject, etc.) contains more or less characters than the specified values, it will be considered spam and it will be blocked. This setting applies ONLY to short text fields, not to textarea fields.");                             
                                                            ?>
                                                        </div>

                                                        <div class="maspik-limit-char-box togglebox">
                                                            <div class = 'maspik-minmax-wrap'>
                                                            <?php 

                                                            echo create_maspik_numbox("text_limit_min", "MinCharactersInTextField", "character-limit" , "Min" ,'' ,1,30);
                                                            
                                                            echo create_maspik_numbox("text_limit_max", "MaxCharactersInTextField", "character-limit" , "Max",'' ,6,1000);

                                                            ?>
                                                            </div>
                                                                    
                                                            <span class="maspik-subtext">
                                                                    <?php esc_html_e("Entries with less than Min or more than Max characters will be blocked. This setting applies ONLY to short text fields (name, subject, etc.), not to textarea fields.", "contact-forms-anti-spam"); ?>
                                                            </span>
                                                    

                                                            <div class="maspik-custom-msg-wrap">
                                                                <div class="maspik-txt-custom-msg-head togglewrap">
                                                                    <?php echo maspik_toggle_button('text_custom_message_toggle', 'text_custom_message_toggle', 'text_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",['custom_error_message_MaxCharactersInTextField']); ?>      
                                                                    <h4> <?php esc_html_e('Character limit custom validation error message', 'contact-forms-anti-spam'); ?> </h4>
                                                                </div>

                                                                <div class="maspik-custom-msg-box togglebox">
                                                                    <?php echo create_maspik_textarea('custom_error_message_MaxCharactersInTextField', 2, 80, 'maspik-textarea', 'error-message'); ?>      
                                                                </div>
                                                                    
                                                            </div><!-- end of maspik-custom-msg-wrap -->

                                                        </div><!-- end of togglebox -->
                                                    </div><!-- end of maspik-limit-char-wrap -->

                                                    <!-- Divider -->
                                                    <div style="margin: 25px 0; border-top: 1px solid #ddd;"></div>

                                                    <!-- Limit Characters for Textarea Fields Only -->
                                                    <div class="maspik-limit-char-wrap">
                                                        <div class="maspik-limit-char-head togglewrap">
                                                            <?php
                                                                    
                                                                echo maspik_toggle_button('textarea_limit_toggle', 'textarea_limit_toggle', 'textarea_limit_toggle', 'maspik-toggle-textarea-limit togglebutton',"","",['MinCharactersInTextAreaField','MaxCharactersInTextAreaField','custom_error_message_MaxCharactersInTextAreaField']);
                                                                    
                                                                echo "<h4>" . esc_html__('Limit Characters - Textarea Fields (Long Text)', 'contact-forms-anti-spam') . "</h4>";

                                                                maspik_tooltip("If the textarea field contains more or less characters than the specified values, it will be considered spam and it will be blocked. This setting applies ONLY to textarea fields (message fields, long text fields), not to short text fields.");                             
                                                            ?>
                                                        </div>

                                                        <div class="maspik-limit-char-box togglebox">

                                                            <div class = 'maspik-minmax-wrap'>
                                                                <?php 

                                                                echo create_maspik_numbox("text_area_limit_min", "MinCharactersInTextAreaField", "character-limit" , "Min",'' ,1,30);
                                                                
                                                                echo create_maspik_numbox("textarea_limit_max", "MaxCharactersInTextAreaField", "character-limit" , "Max", '', 6,100000) 
                                                                ?>
                                                            </div>
                                                                    
                                                            <span class="maspik-subtext">
                                                                    <?php esc_html_e("Entries with less than Min or more than Max characters will be blocked. This setting applies ONLY to textarea fields (message fields, long text fields).", "contact-forms-anti-spam"); ?>
                                                            </span>

                                                            <div class="maspik-custom-msg-wrap">
                                                                <div class="maspik-txt-custom-msg-head togglewrap">
                                                                    <?php echo maspik_toggle_button('textarea_custom_message_toggle', 'textarea_custom_message_toggle', 'textarea_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",['custom_error_message_MaxCharactersInTextAreaField']); ?>
                                                                    <h4><?php esc_html_e('Character limit custom validation error message', 'contact-forms-anti-spam'); ?></h4>
                                                                </div>

                                                                <div class="maspik-custom-msg-box togglebox">
                                                                    <?php echo create_maspik_textarea('custom_error_message_MaxCharactersInTextAreaField', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                                </div>
                                                                    
                                                            </div><!-- end of maspik-custom-msg-wrap -->

                                                        </div><!-- end of togglebox -->
                                                    </div><!-- end of maspik-limit-char-wrap -->
                                                </div>
                                                <!-- End Character Limits Section -->

                                                <?php maspik_save_button_show() ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Accordion Item - Email Field - Custom -->
                                    <div class="maspik-accordion-item maspik-accordion-email-field">
                                        <div class="maspik-accordion-header">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e('Email Fields', 'contact-forms-anti-spam'); ?></h4><!--Accordion Title-->
                                                <span class="maspik-accordion-subheader"></span>
                                            </div>
                                                <span class="maspik-acc-arrow">
                                                    <span class="dashicons dashicons-arrow-right"></span>
                                                </span>
                                        </div>
                                            
                                        <div class="maspik-accordion-content">
                                            <div class="maspik-accordion-content-wrap hide-form-title">
                                                <div class="maspik-setting-info">
                                                    <?php 
                                                    maspik_tooltip("If the text value is CONTAIN to one of the values above, MASPIK will tag it as spam and it will be blocked. you can add wildcard patterns like test@*.ru will block test@mail.ru");
                                                        
                                                    maspik_popup("@test.com|ericjonesonline@|*.ru|*+*@*.*|/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.ru\b/|eric*@*.com|xrumer888@|test@spam.com", "Email field", "See examples" ,"visibility");
                                                        ?>
                                                </div> <!--end of maspik-setting-info-->
                                                    
                                                <div class="maspik-main-list-wrap maspik-textfield-list">

                                                <label for="emails_blacklist"><b><?php esc_html_e('Forbidden email domains/patterns (one per line):', 'contact-forms-anti-spam'); ?></b></label>
                                                <?php
                                                    echo create_maspik_textarea('emails_blacklist', 6, 80, 'maspik-textarea');
                                                        
                                                        maspik_spam_api_list('email_field');
                                                    ?>      

                                                </div> <!-- end of maspik-main-list-wrap -->
                                                <div class="maspik-subtext">
                                                    <h5><?php esc_html_e('How to use block email fields with this option?', 'contact-forms-anti-spam'); ?></h5>
                                                    <ul class="methods-list maspik-list">
                                                        <li><?php esc_html_e('Block specific email: Enter the complete email address (e.g: info@speed-seo.net)', 'contact-forms-anti-spam'); ?></li>
                                                        <li><?php esc_html_e('Use part of email: Enter the part of the email (e.g: @gmail.com) to block all emails that contain this part @gmail.com, like test@gmail.com', 'contact-forms-anti-spam'); ?></li>
                                                        <li><?php esc_html_e('For advanced users - use wildcards (*) or regular expressions (/pattern/) to create flexible blocking patterns', 'contact-forms-anti-spam'); ?></li>
                                                    </ul>
                                                </div>
                                                        
                                                <?php maspik_save_button_show() ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Accordion Item - URL Field Blocker - Custom -->
                                    <div class="maspik-accordion-item maspik-accordion-url-field">
                                        <div class="maspik-accordion-header">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text">
                                                    <?php esc_html_e('URL Field', 'contact-forms-anti-spam'); ?>
                                                </h4>
                                                <span class="maspik-accordion-subheader">
                                                    <?php esc_html_e('Block URL field from forbidden domains/keywords', 'contact-forms-anti-spam'); ?>
                                                </span>
                                            </div>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                        </div>
                                        <div class="maspik-accordion-content">
                                            <div class="maspik-accordion-content-wrap hide-form-title">
                                                <div class="maspik-setting-info">
                                                    <?php maspik_tooltip(__('Any keywords/domain entered here, if found in a URL field, will cause the submission/comment to be marked as spam.', 'contact-forms-anti-spam')); ?>
                                                </div>
                                                <div class="">
                                                    <div class="maspik-url-blacklist-wrap">
                                                        <label for="url_blacklist"><b><?php esc_html_e('Forbidden URL keywords/domains (one per line):', 'contact-forms-anti-spam'); ?></b></label>
                                                        <?php echo create_maspik_textarea('url_blacklist', 4, 80, 'maspik-textarea', 'example.com&#10;bit.ly&#10;spamdomain.com'); 
                                                        maspik_spam_api_list('url_field');
                                                        ?>
                                                        <span class="maspik-subtext">
                                                            <?php esc_html_e('If the URL field contains any of these, the submission will be blocked.', 'contact-forms-anti-spam'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php maspik_save_button_show(); ?>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Accordion Item - Phone Field - Custom -->
                                    <div class="maspik-accordion-item maspik-accordion-phone-field">
                                        <div class="maspik-accordion-header">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e('Whitelist Phone Fields formts', 'contact-forms-anti-spam'); ?></h4><!--Accordion Title-->
                                            </div>
                                                <span class="maspik-acc-arrow">
                                                    <span class="dashicons dashicons-arrow-right"></span>
                                                </span>
                                        </div>
                                            
                                        <div class="maspik-accordion-content">
                                            <div class="maspik-accordion-content-wrap hide-form-title">
                                                <div class="maspik-setting-info">
                                                    <?php 
                                                        maspik_tooltip("List of accepted phone formats, one per line; if the phone field contains a phone number that does not fit into one of the following formats, it will be marked as spam.");
                                                        echo '<span class="help-text">'.esc_html__("Only the following phone formats will be allowed.", 'contact-forms-anti-spam');
                                                        echo "<br>".esc_html__("leave empty to disable this option.", 'contact-forms-anti-spam');
                                                        echo "</span>";  
                                                        maspik_popup("???-???-????|+*|+[1-9]-*|{+*-*,???-???-????}|[0-9][0-9][0-9]-*|/[0-9]{3}-[0-9]{3}-[0-9]{4}/|0*", "Phone field", "See examples" ,"visibility");
                                                    ?>
                                                </div> <!--end of maspik-setting-info-->
                                                        
                                                <div class="maspik-main-list-wrap maspik-textfield-list">

                                                    <label for="tel_formats"><b><?php esc_html_e('Allowed phone formats (one per line):', 'contact-forms-anti-spam'); ?></b></label>
                                                    <?php 
                                                        echo create_maspik_textarea('tel_formats', 6, 80, 'maspik-textarea');
                                                        maspik_spam_api_list('phone_format');
                                                    ?>   

                                                </div> <!-- end of maspik-main-list-wrap -->
                                                <span class="maspik-subtext">
                                                <?php esc_html_e('? represents any single digit.', 'contact-forms-anti-spam'); ?><br>
                                                <?php esc_html_e('* represents any sequence of digits.', 'contact-forms-anti-spam'); ?><br>
                                                    <?php esc_html_e(' You can get more information', 'contact-forms-anti-spam'); ?>
                                                    <a href="https://wpmaspik.com/documentation/phone-field/" target="_blank">
                                                    <?php esc_html_e('HERE', 'contact-forms-anti-spam'); ?></a>    
                                                </span>

                                                <div class="maspik-custom-msg-wrap">
                                                    <div class="maspik-txt-custom-msg-head togglewrap">
                                                        <?php echo maspik_toggle_button('phone_custom_message_toggle', 'phone_custom_message_toggle', 'phone_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",['custom_error_message_tel_formats']); ?>
                                                            
                                                        <h4><?php esc_html_e('Custom validation error message', 'contact-forms-anti-spam'); ?></h4>
                                                    </div>

                                                    <div class="maspik-custom-msg-box togglebox">
                                                        <?php echo create_maspik_textarea('custom_error_message_tel_formats', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                            
                                                    </div>
                                                            
                                                </div><!-- end of maspik-custom-msg-wrap -->

                                                <div class="maspik-limit-char-wrap">
                                                    <div class="maspik-limit-char-head togglewrap">
                                                        <?php
                                                                
                                                            echo maspik_toggle_button('tel_limit_toggle', 'tel_limit_toggle', 'tel_limit_toggle', 'maspik-toggle-tel-limit togglebutton',"","",['MinCharactersInPhoneField',"MaxCharactersInPhoneField","custom_error_message_MaxCharactersInPhoneField"]);
                                                                
                                                            echo '<h4>' . esc_html__('Limit Characters', 'contact-forms-anti-spam') . '</h4>';

                                                            maspik_tooltip("If the text field contains more characters that this value, it will be considered spam and it will be blocked.");                             
                                                        ?>
                                                    </div>

                                                    <div class="maspik-limit-char-box togglebox">
                                                        <div class = 'maspik-minmax-wrap'>
                                                            <?php 

                                                            echo create_maspik_numbox("phone_limit_min", "MinCharactersInPhoneField", "character-limit" , "Min");
                                                            
                                                            echo create_maspik_numbox("phone_limit_max", "MaxCharactersInPhoneField", "character-limit" , "Max");

                                                            ?>
                                                        </div>
                                                                
                                                        <span class="maspik-subtext">
                                                                <?php esc_html_e("Entries with less than Min or more than Max characters will be blocked", "contact-forms-anti-spam"); ?>
                                                        </span>
                                                    
                                                        <div class="maspik-custom-msg-wrap">
                                                            <div class="maspik-txt-custom-msg-head togglewrap">
                                                                <?php echo maspik_toggle_button('phone_limit_custom_message_toggle', 'phone_limit_custom_message_toggle', 'phone_limit_custom_message_toggle', 'maspik-toggle-custom-message togglebutton',"","",["custom_error_message_MaxCharactersInPhoneField"]); ?>
                                                                <h4><?php esc_html_e('Character limit custom validation error message', 'contact-forms-anti-spam'); ?></h4>
                                                            </div>

                                                            <div class="maspik-custom-msg-box togglebox">
                                                                <?php echo create_maspik_textarea('custom_error_message_MaxCharactersInPhoneField', 2, 80, 'maspik-textarea', 'error-message'); ?>
                                                                
                                                            </div>
                                                                
                                                        </div><!-- end of maspik-custom-msg-wrap -->


                                                    </div><!-- end of togglebox -->
                                                </div><!-- end of maspik-limit-char-wrap -->                                    

                                                <?php maspik_save_button_show() ?>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- MORE OPTIONS HEADER -->
                                    <div class="maspik-section-headhead maspik-more-setting"> 
                                        <h2 class='maspik-title maspik-bl-header'><?php esc_html_e('More Options', 'contact-forms-anti-spam'); ?></h2>
                                    </div>
                                    <!-- MORE OPTIONS HEADER - END -->

                                     <!-- Accordion Item - Maspik Matrix -->
                                     <div class="maspik-accordion-item maspik-accordion-ai-spam-check" id="maspik-matrix-section">
                                        <div class="maspik-accordion-header" id="ai-spam-check-accordion">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text">
                                                    <?php esc_html_e('Maspik Matrix options and Logs', 'contact-forms-anti-spam'); ?>
                                                    <span class="maspik-beta-badge">New</span>
                                                </h4>
                                            </div>
                                            <div class="maspik-pro-button-wrap">
                                                <span class="maspik-acc-arrow">
                                                    <span class="dashicons dashicons-arrow-right"></span>
                                                </span>
                                            </div>
                                        </div>
                                            
                                        <div class="maspik-accordion-content" id="maspik-ai-spam-check">
                                            <div class="maspik-accordion-content-wrap maspik-matrix-accordion-wrap">

                                                <!-- Matrix explainer -->
                                                <div class="maspik-matrix-surface maspik-matrix-surface--intro maspik-matrix-intro">
                                                    <h3 class="maspik-accordion-subtitle">
                                                        <?php esc_html_e( 'What is Maspik Matrix?', 'contact-forms-anti-spam' ); ?>
                                                    </h3>
                                                    <p class="maspik-subtext">
                                                        <?php esc_html_e('Maspik Matrix is a powerful multi-layer spam protection engine that combines multiple detection methods into one smart protection system.', 'contact-forms-anti-spam'); ?>
                                                   
                                                        <?php
                                                        echo sprintf(
                                                            esc_html__('We recommend to keep this feature enabled and read the documentation %shere%s.', 'contact-forms-anti-spam'),
                                                            '<a href="https://wpmaspik.com/documentation/ai-spam-check/" target="_blank">',
                                                            '</a>'
                                                        );
                                                        ?>
                                                    
                                                        <?php esc_html_e('You can turn Matrix on or off from the toggle in the "Main Options" section above.', 'contact-forms-anti-spam'); ?>
                                                    </p>
                                                </div>

                                                <!-- Section 1: Short prompt -->
                                                <div class="maspik-matrix-surface maspik-matrix-section maspik-matrix-section--prompt">
                                                    <h3 class="maspik-accordion-subtitle">
                                                        <?php esc_html_e( '1. Short prompt (Business context)', 'contact-forms-anti-spam' ); ?>
                                                    </h3>
                                                    <p class="maspik-subtext">
                                                        <?php esc_html_e( 'Optional but recommended: tell Matrix what your business does so AI can better tell real leads from spam.', 'contact-forms-anti-spam' ); ?>
                                                    </p>

                                                    <div class="maspik-field-group">
                                                        <label for="maspik_ai_context"><?php esc_html_e('Business context / Short prompt', 'contact-forms-anti-spam'); ?></label>
                                                        <?php echo create_maspik_textarea('maspik_ai_context', 2, 50, 'ai-context', 'Example: "Business sells used cars in the US. Block messages in other languages or unrelated investment offers."', 170 ); ?>
                                                        <p class="maspik-field-description">
                                                            <?php esc_html_e('Describe your business in one short sentence. This helps AI understand what is a normal inquiry for you and what looks like spam. Max 170 characters.', 'contact-forms-anti-spam'); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Section 2: API check depth (mode) -->
                                                <div class="maspik-matrix-surface maspik-matrix-section maspik-matrix-section--mode">
                                                    <h3 class="maspik-accordion-subtitle">
                                                        <?php esc_html_e( '2. Matrix mode (What to check?)', 'contact-forms-anti-spam' ); ?>
                                                    </h3>
                                                    <p class="maspik-subtext maspik-matrix-mode-intro">
                                                        <?php esc_html_e( 'Choose how deep each Matrix API request runs. Lower levels skip some checks and can reduce latency or cost; full mode applies the complete spam pipeline (recommended).', 'contact-forms-anti-spam' ); ?>
                                                    </p>
                                                    <?php
                                                    $matrix_mode_val = maspik_get_settings( 'maspik_matrix_api_mode' );
                                                    $matrix_mode_val = ( $matrix_mode_val === null || $matrix_mode_val === '' ) ? 4 : absint( $matrix_mode_val );
                                                    if ( ! in_array( $matrix_mode_val, array( 2, 3, 4 ), true ) ) {
                                                        $matrix_mode_val = 4;
                                                    }
                                                    ?>
                                                    <fieldset class="maspik-matrix-mode-fieldset">
                                                        <legend class="screen-reader-text"><?php esc_html_e( 'Matrix check depth', 'contact-forms-anti-spam' ); ?></legend>
                                                        <div class="maspik-matrix-mode-stack" role="presentation">
                                                            <label class="maspik-matrix-mode-card">
                                                                <input type="radio" class="maspik-matrix-mode-radio" name="maspik_matrix_api_mode" value="2" <?php checked( $matrix_mode_val, 2 ); ?> />
                                                                <span class="maspik-matrix-mode-card__main">
                                                                    <span class="maspik-matrix-mode-card__title-row">
                                                                        <span class="maspik-matrix-mode-card__title"><?php esc_html_e( 'IP reputation only', 'contact-forms-anti-spam' ); ?></span>
                                                                        <span class="maspik-matrix-mode-badge maspik-matrix-mode-badge--catch maspik-matrix-mode-badge--catch-50 maspik-matrix-mode-card__pct-pill"><?php printf( esc_html__( 'Up to %d%% spam caught', 'contact-forms-anti-spam' ), 40 ); ?></span>
                                                                    </span>
                                                                    <span class="maspik-matrix-mode-card__desc"><?php esc_html_e( 'Only IP check. No form Data is sent to Maspik server.', 'contact-forms-anti-spam' ); ?></span>
                                                                </span>
                                                            </label>
                                                            <label class="maspik-matrix-mode-card">
                                                                <input type="radio" class="maspik-matrix-mode-radio" name="maspik_matrix_api_mode" value="3" <?php checked( $matrix_mode_val, 3 ); ?> />
                                                                <span class="maspik-matrix-mode-card__main">
                                                                    <span class="maspik-matrix-mode-card__title-row">
                                                                        <span class="maspik-matrix-mode-card__title"><?php esc_html_e( 'IP reputation + banned words', 'contact-forms-anti-spam' ); ?></span>
                                                                        <span class="maspik-matrix-mode-badge maspik-matrix-mode-badge--catch maspik-matrix-mode-badge--catch-65 maspik-matrix-mode-card__pct-pill"><?php printf( esc_html__( 'Up to %d%% spam caught', 'contact-forms-anti-spam' ), 60 ); ?></span>
                                                                    </span>
                                                                    <span class="maspik-matrix-mode-card__desc"><?php esc_html_e( 'Check field text against banned-word lists and IP reputation.', 'contact-forms-anti-spam' ); ?></span>
                                                                </span>
                                                            </label>
                                                            <label class="maspik-matrix-mode-card">
                                                                <input type="radio" class="maspik-matrix-mode-radio" name="maspik_matrix_api_mode" value="4" <?php checked( $matrix_mode_val, 4 ); ?> />
                                                                <span class="maspik-matrix-mode-card__main">
                                                                    <span class="maspik-matrix-mode-card__title-row">
                                                                        <span class="maspik-matrix-mode-card__title"><?php esc_html_e( 'Full Matrix analysis', 'contact-forms-anti-spam' ); ?></span>
                                                                        <span class="maspik-matrix-mode-badge maspik-matrix-mode-badge--catch maspik-matrix-mode-badge--catch-90 maspik-matrix-mode-card__pct-pill"><?php printf( esc_html__( 'Up to %d%% spam caught', 'contact-forms-anti-spam' ), 90 ); ?></span>
                                                                    </span>
                                                                    <span class="maspik-matrix-mode-card__desc"><?php esc_html_e( 'Full pipeline: heuristics, patterns, AI, and related checks (default).', 'contact-forms-anti-spam' ); ?></span>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    </fieldset>
                                                </div>

                                                <!-- Section 3: Usage -->
                                                <?php
                                                // Simple AI metrics summary list (recent months)
                                                $ai_metrics = maspik_get_settings( 'maspik_ai_metrics' );
                                                if ( is_array( $ai_metrics ) && ! empty( $ai_metrics['by_month'] ) ) :
                                                    $months_data = $ai_metrics['by_month'];
                                                    krsort( $months_data ); // newest first
                                                    $months_data = array_slice( $months_data, 0, 6, true );
                                                ?>
                                                <div class="maspik-matrix-surface maspik-ai-metrics-panel maspik-matrix-section maspik-matrix-section--usage">
                                                    <h3 class="maspik-accordion-subtitle">
                                                        <?php esc_html_e( '3. Matrix usage', 'contact-forms-anti-spam' ); ?>
                                                    </h3>
                                                    <p class="maspik-subtext">
                                                        <?php esc_html_e( 'See how often Matrix checks submissions and how many are blocked as spam.', 'contact-forms-anti-spam' ); ?>
                                                    </p>
                                                    <div class="maspik-matrix-table-scroll">
                                                    <table class="widefat maspik-ai-metrics-table maspik-matrix-data-table">
                                                        <thead>
                                                            <tr>
                                                                <th><?php esc_html_e( 'Month', 'contact-forms-anti-spam' ); ?></th>
                                                                <th><?php esc_html_e( 'Checks', 'contact-forms-anti-spam' ); ?></th>
                                                                <th><?php esc_html_e( 'Spam', 'contact-forms-anti-spam' ); ?></th>
                                                                <th><?php esc_html_e( 'Spam rate', 'contact-forms-anti-spam' ); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ( $months_data as $ym => $data ) :
                                                                $checks = isset( $data['checks'] ) ? (int) $data['checks'] : 0;
                                                                $spam   = isset( $data['spam'] ) ? (int) $data['spam'] : 0;
                                                                $rate   = $checks > 0 ? round( ( $spam / $checks ) * 100 ) : 0;
                                                                // Format month label from YYYYMM
                                                                $year  = substr( $ym, 0, 4 );
                                                                $month = substr( $ym, 4, 2 );
                                                                $month_label = date_i18n( 'F Y', strtotime( $year . '-' . $month . '-01' ) );
                                                            ?>
                                                            <tr>
                                                                <td><?php echo esc_html( $month_label ); ?></td>
                                                                <td><?php echo esc_html( number_format_i18n( $checks ) ); ?></td>
                                                                <td><?php echo esc_html( number_format_i18n( $spam ) ); ?></td>
                                                                <td><?php echo esc_html( $rate ); ?>%</td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    </div>
                                                </div>
                                                <?php endif; ?>

                                                <!-- Matrix logs -->
                                                <div class="maspik-matrix-surface maspik-ai-logs-table-wrap maspik-matrix-section maspik-matrix-section--logs">
                                                    <h3 class="maspik-accordion-subtitle">
                                                        <?php esc_html_e( 'Matrix logs (last checks)', 'contact-forms-anti-spam' ); ?>
                                                    </h3>
                                                    <p class="maspik-subtext">
                                                        <?php esc_html_e( 'Use the logs to debug why a specific submission was blocked or allowed.', 'contact-forms-anti-spam' ); ?>
                                                    </p>

                                                    <div class="maspik-ai-logs-header">
                                                        <button type="button" class="maspik-ai-logs-table-button button button-secondary">
                                                            <?php esc_html_e('Show Maspik Matrix Logs', 'contact-forms-anti-spam'); ?>
                                                        </button>
                                                        
                                                        <?php
                                                        $ai_logs = maspik_get_ai_logs();
                                                        
                                                        if ( !empty($ai_logs) ) : ?>
                                                            <button type="button" class="maspik-clear-ai-logs button button-link-delete">
                                                                <?php esc_html_e('Clear Logs', 'contact-forms-anti-spam'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="maspik-ai-logs-table-container" style="display: none;">
                                                        <h4><?php esc_html_e('Last 10 Maspik Matrix Results', 'contact-forms-anti-spam'); ?></h4>
                                                        
                                                        <?php if ( !empty($ai_logs) ) : ?>
                                                            <div class="maspik-matrix-table-scroll maspik-matrix-table-scroll--wide">
                                                            <table class="maspik-ai-logs-table widefat maspik-matrix-data-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?php esc_html_e('Time', 'contact-forms-anti-spam'); ?></th>
                                                                        <th><?php esc_html_e('IP Address', 'contact-forms-anti-spam'); ?></th>
                                                                        <th><?php esc_html_e('Fields', 'contact-forms-anti-spam'); ?></th>
                                                                        <th><?php esc_html_e('AI Score', 'contact-forms-anti-spam'); ?></th>
                                                                        <th><?php esc_html_e('Result', 'contact-forms-anti-spam'); ?></th>
                                                                        <th><?php esc_html_e('Reason', 'contact-forms-anti-spam'); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ( $ai_logs as $log ) :
                                                                            // Extract all log data into convenient variables
                                                                            
                                                                            // Basic log info
                                                                            $timestamp = $log['timestamp'] ?? '';
                                                                            $ip_address = $log['ip_address'] ?? '';
                                                                            $fields = $log['fields'] ?? [];
                                                                            
                                                                            // AI response data
                                                                            $ai_response = $log['ai_response'] ?? [];
                                                                            $is_error = isset($ai_response['error']) && $ai_response['error'] == true;
                                                                            
                                                                            // Result data
                                                                            $result = $log['result'] ?? [];
                                                                            $is_allowed = $result['allow'] ?? false;
                                                                            
                                                                            // AI response details
                                                                            $response_data = $ai_response['response'] ?? [];
                                                                            // Prefer top-level spam_score (new API), fall back to legacy response.spam_score
                                                                            if ( isset( $ai_response['spam_score'] ) ) {
                                                                                $spam_score = (int) $ai_response['spam_score'];
                                                                            } elseif ( array_key_exists( 'spam_score', $response_data ) ) {
                                                                                $spam_score = (int) $response_data['spam_score'];
                                                                            } else {
                                                                                $spam_score = null;
                                                                            }
                                                                            // Prefer user_reason from new API, then top-level reason, then legacy response.reason
                                                                            if ( isset( $ai_response['user_reason'] ) && $ai_response['user_reason'] !== '' ) {
                                                                                $reason = $ai_response['user_reason'];
                                                                            } elseif ( isset( $ai_response['reason'] ) && $ai_response['reason'] !== '' ) {
                                                                                $reason = $ai_response['reason'];
                                                                            } else {
                                                                                $reason = $response_data['reason'] ?? '';
                                                                            }
                                                                            $field_errors = $response_data['field_errors'] ?? [];
                                                                            
                                                                            // Error details (if error)
                                                                            $error_detail = $ai_response['error_detail'] ?? '';
                                                                            $http_code = $ai_response['http_code'] ?? '';
                                                                            $response_body = $ai_response['response_body'] ?? '';
                                                                            $response_headers = $ai_response['response_headers'] ?? [];
                                                                            $error_timestamp = $ai_response['timestamp'] ?? '';
                                                                            
                                                                            // Format timestamp for display
                                                                            $formatted_time = $timestamp ? date('Y-m-d H:i:s', strtotime($timestamp)) : 'N/A';
                                                                            
                                                                            // Determine result status
                                                                            $result_status = $is_error ? 'Error' : ($is_allowed ? 'Allowed' : 'Blocked');
                                                                            $result_class = $is_error ? 'error' : ($is_allowed ? 'allow' : 'block');
                                                                            
                                                                            ?>
                                                                             <tr>
                                                                                 <td><?php echo esc_html($formatted_time); ?></td>
                                                                                 <td><?php echo esc_html($ip_address); ?></td>
                                                                                 <td>
                                                                                     <button type="button" class="button button-small maspik-view-fields" data-fields='<?php echo esc_attr(wp_json_encode($fields)); ?>'>
                                                                                         <?php esc_html_e('View Fields', 'contact-forms-anti-spam'); ?>
                                                                                     </button>
                                                                                 </td>
                                                                                 <td>
                                                                                     <span class="maspik-score-<?php echo esc_attr($spam_score === null ? 'na' : $spam_score); ?>">
                                                                                         <?php echo esc_html($spam_score === null ? 'N/A' : $spam_score); ?>
                                                                                     </span>
                                                                                 </td>
                                                                                 <td>
                                                                                     <span class="maspik-result-<?php echo esc_attr($result_class); ?>">
                                                                                         <?php echo esc_html($result_status); ?>
                                                                                     </span>
                                                                                 </td>
                                                                                 <td>
                                                                                     <?php 
                                                                                     if ($is_error) {
                                                                                         // Display error information in a readable format
                                                                                         echo '<div class="maspik-error-response">';
                                                                                         echo '<strong>Error:</strong> ' . esc_html($error_detail ?: 'Unknown error') . '<br>';
                                                                                         
                                                                                         if ($http_code) {
                                                                                             echo '<strong>HTTP Code:</strong> ' . esc_html($http_code) . '<br>';
                                                                                         }
                                                                                         
                                                                                         if ($response_body) {
                                                                                             // Try to decode JSON for better display
                                                                                             $json_body = json_decode($response_body, true);
                                                                                             if ($json_body) {
                                                                                                 echo '<strong>Response:</strong><br>';
                                                                                                 echo '<pre style="font-size: 11px; background: #f1f1f1; padding: 5px; margin: 5px 0; border-radius: 3px; max-height: 100px; overflow-y: auto;">';
                                                                                                 echo esc_html(json_encode($json_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                                                                                 echo '</pre>';
                                                                                             } else {
                                                                                                 echo '<strong>Response:</strong> ' . esc_html(substr($response_body, 0, 200)) . (strlen($response_body) > 200 ? '...' : '');
                                                                                             }
                                                                                         }
                                                                                         
                                                                                         if ($error_timestamp) {
                                                                                             echo '<strong>Time:</strong> ' . esc_html($error_timestamp);
                                                                                         }
                                                                                         
                                                                                         echo '</div>';
                                                                                     } else {
                                                                                         // Display successful response
                                                                                         if ($reason) {
                                                                                             echo '<div class="maspik-success-response">';
                                                                                             echo '<strong>Reason:</strong> ' . esc_html($reason) . '<br>';
                                                                                             
                                                                                             if (!empty($field_errors)) {
                                                                                                 echo '<strong>Field Errors:</strong><br>';
                                                                                                 echo '<ul style="margin: 5px 0; padding-left: 15px;">';
                                                                                                 foreach ($field_errors as $field => $error) {
                                                                                                     echo '<li><strong>' . esc_html($field) . ':</strong> ' . esc_html($error) . '</li>';
                                                                                                 }
                                                                                                 echo '</ul>';
                                                                                             }
                                                                                             
                                                                                             echo '</div>';
                                                                                         } else {
                                                                                             echo '<span class="maspik-no-reason">No detailed reason provided</span>';
                                                                                         }
                                                                                     }
                                                                                     ?></td>
                                                                             </tr>
                                                                         <?php endforeach; ?>
                                                                     </tbody>
                                                                 </table>
                                                            </div>
                                                             <?php else : ?>
                                                                 <p><?php esc_html_e('No AI logs found yet.', 'contact-forms-anti-spam'); ?></p>
                                                             <?php endif; ?>
                                                         </div>
                                                     </div>

                                                <script>
                                                 jQuery(document).ready(function($) {
                                                     // Toggle AI logs table
                                                     $('.maspik-ai-logs-table-button').on('click', function() {
                                                         var $container = $('.maspik-ai-logs-table-container');
                                                         var $button = $(this);
                                                         
                                                         if ($container.is(':visible')) {
                                                             $container.hide();
                                                             $button.text('<?php esc_html_e('Show Matrix Logs', 'contact-forms-anti-spam'); ?>');
                                                         } else {
                                                             $container.show();
                                                             $button.text('<?php esc_html_e('Hide Matrix Logs', 'contact-forms-anti-spam'); ?>');
                                                         }
                                                     });
                                                     
                                                     // Clear AI logs
                                                     $('.maspik-clear-ai-logs').on('click', function() {
                                                         if (confirm('<?php esc_html_e('Are you sure you want to Delete all Matrix logs?', 'contact-forms-anti-spam'); ?>')) {
                                                             $.post(ajaxurl, {
                                                                 action: 'maspik_clear_ai_logs',
                                                                 nonce: '<?php echo wp_create_nonce('maspik_clear_ai_logs'); ?>'
                                                             }, function(response) {
                                                                 if (response.success) {
                                                                     location.reload();
                                                                 } else {
                                                                     alert('<?php esc_html_e('Failed to clear logs', 'contact-forms-anti-spam'); ?>');
                                                                 }
                                                             });
                                                         }
                                                     });
                                                     
                                                     // View fields modal
                                                     $('.maspik-view-fields').on('click', function() {
                                                         var fields = $(this).data('fields');
                                                         var fieldsHtml = '';
                                                         
                                                         for (var key in fields) {
                                                             if (fields.hasOwnProperty(key)) {
                                                                 fieldsHtml += '<tr><td><strong>' + key + '</strong></td><td>' + fields[key] + '</td></tr>';
                                                             }
                                                         }
                                                         
                                                         var modalHtml = '<div class="maspik-modal-overlay">' +
                                                                         '<div class="maspik-modal">' +
                                                                         '<div class="maspik-modal-header">' +
                                                                         '<h3><?php esc_html_e('Form Fields', 'contact-forms-anti-spam'); ?></h3>' +
                                                                         '<button class="maspik-modal-close">&times;</button>' +
                                                                         '</div>' +
                                                                         '<div class="maspik-modal-body">' +
                                                                         '<table class="widefat">' +
                                                                         '<thead><tr><th><?php esc_html_e('Field', 'contact-forms-anti-spam'); ?></th><th><?php esc_html_e('Value', 'contact-forms-anti-spam'); ?></th></tr></thead>' +
                                                                         '<tbody>' + fieldsHtml + '</tbody>' +
                                                                         '</table>' +
                                                                         '</div>' +
                                                                         '</div>' +
                                                                         '</div>';
                                                         
                                                         $('body').append(modalHtml);
                                                     });
                                                     
                                                     // Close modal
                                                     $(document).on('click', '.maspik-modal-close, .maspik-modal-overlay', function() {
                                                         $('.maspik-modal-overlay').remove();
                                                     });
                                                 });
                                                </script>

                                                <?php maspik_save_button_show() ?>

                                            </div>
                                        </div>
                                    </div>

                                    <!-- Accordion Item - Other Options Field - Custom -->
                                    <div class="maspik-accordion-item maspik-accordion-other-option-field" >
                                        <div class="maspik-accordion-header" id="ip-blacklist-accordion">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e('IP Blacklist and 3rd Party APIs', 'contact-forms-anti-spam'); ?></h4><!--Accordion Title-->
                                            </div>
                                            <div class = "maspik-pro-button-wrap">
                                                <span class="maspik-acc-arrow">
                                                    <span class="dashicons dashicons-arrow-right"></span>
                                                </span>
                                            </div>
                                        </div>
                                            
                                        <div class="maspik-accordion-content" id="maspik-form-options">
                                            <div class="maspik-accordion-content-wrap hide-form-title">
                                                <div class="maspik-accordion-subtitle-wrap short-tooltip">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e("List of block IPs", 'contact-forms-anti-spam'); ?></h3>
                                                    <?php 
                                                        maspik_tooltip("Any IP you enter above will be blocked.One IP per line.");
                                                    ?>
                                                </div> <!--end of maspik-accordion-subtitle-wrap-->
                                                <div class="maspik-ip-wrap maspik-main-list-wrap maspik-textfield-list">
                                                    <label for="ip_blacklist"><b><?php esc_html_e('Blocked IP addresses (one per line):', 'contact-forms-anti-spam'); ?></b></label>
                                                    <?php
                                                        echo create_maspik_textarea('ip_blacklist', 6, 80, 'maspik-textarea');
                                                        maspik_spam_api_list('ip');
                                                    ?> 
                                                </div> <!-- end of maspik-ip-wrap  -->
                                                <span class="maspik-subtext"><?php esc_html_e('You can also filter entire CIDR range such as 134.209.0.0/16', 'contact-forms-anti-spam'); ?></span>

                                                <!---- 3rd party API divider S---------->
                                                <div class = 'maspik-simple-divider'></div>
                                                <!---- 3rd party API divider E---------->

                                                <div class="maspik-accordion-subtitle-wrap short-tooltip">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e('AbuseIPDB API', 'contact-forms-anti-spam'); ?></h3>
                                                    <?php 
                                                        maspik_tooltip("AbuseIPDB.com API Recommend not lower than 25 for less false positives. We recommend setting threshold between 70-100 based on your needs.");
                                                    ?>
                                                </div> <!--end of maspik-accordion-subtitle-wrap-->
                                                

                                                <div class="maspik-abuse-api-wrap maspik-main-list-wrap maspik-textfield-list">

                                                    <?php echo create_maspik_input('abuseipdb_api', 'maspik-inputbox'); ?>
                                                    <div class="maspik-threshold-wrap">
                                                    <?php echo create_maspik_numbox("abuseipdb_score", "abuseipdb_score", "threshold-limit" , "Risk Threshold", "") ?>
                                                    </div>

                                                </div> <!-- end of maspik-abuse-api-wrap  -->
                                            
                                                <span class="maspik-subtext"><?php esc_html_e('For more infromation', 'contact-forms-anti-spam'); ?> <a target = "_blank" href="https://www.abuseipdb.com/?Maspik-plugin">
                                                <?php esc_html_e('AbuseIPDB', 'contact-forms-anti-spam'); ?></a></span>
                                                <span class="maspik-subtext"><?php esc_html_e('Leave blank to disable', 'contact-forms-anti-spam'); ?>.</span>
                                                    <?php maspik_spam_api_list('abuseipdb_api');?>

                                                <div class="maspik-accordion-subtitle-wrap short-tooltip add-space-top">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e('Proxycheck.io API', 'contact-forms-anti-spam'); ?></h3>
                                                    <?php 
                                                        maspik_tooltip("Proxycheck.io API risk score: 0-50 may have false positives. Scores above 70 indicate higher reliability in detecting proxy/VPN usage. We recommend setting threshold between 70-100 based on your needs.");
                                                    ?>
                                                </div> <!--end of maspik-accordion-subtitle-wrap-->


                                                <div class="maspik-abuse-api-wrap maspik-main-list-wrap maspik-textfield-list">

                                                    <?php echo create_maspik_input('proxycheck_io_api', 'maspik-inputbox'); ?>
                                                    <div class="maspik-threshold-wrap">
                                                    <?php echo create_maspik_numbox("proxycheck_io_risk", "proxycheck_io_risk", "threshold-limit" , "Risk Threshold", "") ?>
                                                    </div>

                                                </div> <!-- end of maspik-abuse-api-wrap  -->
                                                <span class="maspik-subtext"><?php esc_html_e('For more infromation', 'contact-forms-anti-spam'); ?> <a target = "_blank" href="https://proxycheck.io/?Maspik-plugin">
                                                <?php esc_html_e('ProxyCheck', 'contact-forms-anti-spam'); ?></a></span>

                                                <span class="maspik-subtext"><?php esc_html_e('Leave blank to disable.', 'contact-forms-anti-spam'); ?></span>
                                                
                                                <?php maspik_spam_api_list('proxycheck_io_api');?>    
                                                
                                                <div class="maspik-accordion-subtitle-wrap short-tooltip add-space-top">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e('Numverify API Key', 'contact-forms-anti-spam'); ?></h3>
                                                    <?php 
                                                        maspik_tooltip("Numverify API is a phone number verification service that checks if a phone number is valid.");
                                                    ?>
                                                </div> <!--end of maspik-accordion-subtitle-wrap-->

                                                <div class="maspik-numverify-api-wrap maspik-main-list-wrap maspik-textfield-list">

                                                    <?php echo create_maspik_input('numverify_api', 'maspik-inputbox'); ?>

                                                </div> <!-- end of maspik-abuse-api-wrap  -->

                                                <span class="maspik-subtext"><?php esc_html_e('By default, Numverify requires phone numbers to include the country code.
                                                If your site serves a specific country and users don\'t enter country codes,
                                                you can select the country code from the list below. Note, if a country code is selected but the user enters different country code,
                                                the number will be invalid because it will contain two country codes.
                                                Please test thoroughly to understand this behavior.', 'contact-forms-anti-spam'); ?></span>
                                                
                                                <span class="maspik-subtext"><?php esc_html_e('For more infromation', 'contact-forms-anti-spam'); ?> <a target = "_blank" href="https://numverify.com/documentation/?Maspik-plugin">
                                                <?php esc_html_e('Numverify documentation', 'contact-forms-anti-spam'); ?></a></span>

                                                <?php maspik_spam_api_list('numverify_api');?>    

                                                <div class="maspik-select-list">
                                                    <div class="maspik-main-list-wrap">
                                                        
                                                        <?php 
                                                            echo create_maspik_select("numverify_country", "numverify_country", $MASPIK_COUNTRIES_LIST_FOR_PHONE , "", false);                                 
                                                        ?> 
                                                    </div>
                                                        
                                                </div> <!-- end of maspik-main-list-wrap -->


                                                    <?php  maspik_save_button_show() ?>
                                                
                                            </div>
                                        </div>
                                    </div>

                                   

                                    <!-- Accordion Item - Form Options Field - Custom -->
                                    <div class="maspik-accordion-item maspik-accordion-form-option-field" >
                                        <div class="maspik-accordion-header" id="form-option-accordion">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e('Supported forms', 'contact-forms-anti-spam'); ?></h4><!--Accordion Title-->
                                            </div>
                                            <div class = "maspik-pro-button-wrap">
                                                <span class="maspik-acc-arrow">
                                                    <span class="dashicons dashicons-arrow-right"></span>
                                                </span>
                                            </div>
                                        </div>
                                            
                                        <div class="maspik-accordion-content" id="maspik-form-options">
                                            <div class="maspik-accordion-content-wrap hide-form-title">


                                                <div class="maspik-cf7-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('contact-form-7') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_cf7', 'maspik_support_cf7', 'maspik_support_cf7', 'maspik-form-switch togglebutton', "form-toggle",efas_if_plugin_is_active('contact-form-7'));?>
                                                    <div>
                                                        <h4> <?php esc_html_e('Support Contact from 7', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-cf7-switch-wrap -->

                                                <div class="maspik-elementor-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('elementor') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_Elementor_forms', 'maspik_support_Elementor_forms', 'maspik_support_Elementor_forms', 'maspik-form-switch togglebutton', "form-toggle",efas_if_plugin_is_active('elementor'));?>
                                                    <div>
                                                        <h4> <?php esc_html_e('Support Elementor forms', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-elementor-switch-wrap -->

                                                <div class="maspik-wp-comment-switch-wrap togglewrap maspik-form-switch-wrap">
                                                    <?php echo maspik_toggle_button('maspik_support_wp_comment', 'maspik_support_wp_comment', 'maspik_support_wp_comment', 'maspik-form-switch togglebutton', "form-toggle", maspik_get_settings( "maspik_support_wp_comment", 'form-toggle' )); ?>
                                                    <div>
                                                        <h4> <?php esc_html_e('Support WP comments', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-wp-comment-switch-wrap -->

                                                <div class="maspik-wp-registration-switch-wrap togglewrap maspik-form-switch-wrap">
                                                    <?php echo maspik_toggle_button('maspik_support_registration', 'maspik_support_registration', 'maspik_support_registration', 'maspik-form-switch togglebutton', "form-toggle", maspik_get_settings( "maspik_support_registration", 'form-toggle' )); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support WP registration', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-wp-registration-switch-wrap -->

                                                <div class="maspik-helloplus-switch-wrap togglewrap maspik-form-switch-wrap  <?php echo efas_if_plugin_is_active('hello-plus') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_helloplus_forms', 'maspik_support_helloplus_forms', 'maspik_support_helloplus_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('hello-plus')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support Hello Plus', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-helloplus-switch-wrap -->

                                                <div class="maspik-formidable-switch-wrap togglewrap maspik-form-switch-wrap  <?php echo efas_if_plugin_is_active('formidable') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_formidable_forms', 'maspik_support_formidable_forms', 'maspik_support_formidable_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('formidable')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support Formidable', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-formidable-switch-wrap -->

                                                <div class="maspik-forminator-switch-wrap togglewrap maspik-form-switch-wrap  <?php echo efas_if_plugin_is_active('forminator') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_forminator_forms', 'maspik_support_forminator_forms', 'maspik_support_forminator_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('forminator')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support Forminator', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-forminator-switch-wrap -->
                                                
                                                <div class="maspik-fluentform-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('fluentforms') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_fluentforms_forms', 'maspik_support_fluentforms_forms', 'maspik_support_fluentforms_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('fluentforms')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support Fluentforms', 'contact-forms-anti-spam'); ?> </h4>
                                                    </div>  
                                                </div><!-- end of maspik-fluentform-switch-wrap -->

                                                                                                <div class="maspik-bricks-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('bricks') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_bricks_forms', 'maspik_support_bricks_forms', 'maspik_support_bricks_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('bricks')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support Bricks forms', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-bricks-switch-wrap -->

                                                <div class="maspik-metform-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('metform') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_metform_forms', 'maspik_support_metform_forms', 'maspik_support_metform_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('metform')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support MetForm', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-metform-switch-wrap -->

                                                <div class="maspik-bitform-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('bitform') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_bitform_forms', 'maspik_support_bitform_forms', 'maspik_support_bitform_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('bitform')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support BitForm', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-bitform-switch-wrap -->

                                                <div class="maspik-breakdance-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('breakdance') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_breakdance_forms', 'maspik_support_breakdance_forms', 'maspik_support_breakdance_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('breakdance')); ?>
                                                        <div>
                                                            <h4> <?php esc_html_e('Support Breakdance Builder forms', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-breakdance-switch-wrap -->


                                                <div class="maspik-support-ninjaforms-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('ninjaforms') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_ninjaforms', 'maspik_support_ninjaforms', 'maspik_support_ninjaforms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('ninjaforms')); ?>
                                                        <div class="wp-reg">
                                                                <h4> <?php esc_html_e('Support Ninja Forms', 'contact-forms-anti-spam'); ?></h4>
                                                                
                                                        </div>  
                                                </div><!-- end of maspik-support-ninjaforms-switch-wrap-->

                                                <div class="maspik-wp-jetform-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('jetforms') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_jetforms', 'maspik_support_jetforms', 'maspik_support_jetforms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('jetforms')); ?>
                                                        <div class="wp-reg">
                                                                <h4> <?php esc_html_e('Support Jet Form', 'contact-forms-anti-spam'); ?></h4>
                                                                
                                                        </div>  
                                                </div><!-- end of maspik-wp-jetform-switch-wrap-->

                                                <div class="maspik-wp-jetform-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('everestforms') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_everestforms', 'maspik_support_everestforms', 'maspik_support_everestforms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('everestforms')); ?>
                                                        <div class="wp-reg">
                                                                <h4> <?php esc_html_e('Support Everest Forms', 'contact-forms-anti-spam'); ?></h4>
                                                                
                                                        </div>  
                                                </div><!-- end of maspik-wp-jetform-switch-wrap-->

                                                <div class="maspik-buddypress-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('buddypress') == 1 ? 'enabled':'disabled' ?>">
                                                    <?php echo maspik_toggle_button('maspik_support_buddypress_forms', 'maspik_support_buddypress_forms', 'maspik_support_buddypress_forms', 'maspik-form-switch togglebutton', "form-toggle", efas_if_plugin_is_active('buddypress')); ?>
                                                        <div class="wp-reg">
                                                                <h4> <?php esc_html_e('Support Buddypress', 'contact-forms-anti-spam'); ?></h4>
                                                        </div>  
                                                </div><!-- end of maspik-wp-jetform-switch-wrap-->

                                                <div class="maspik-custom-switch-wrap togglewrap maspik-form-switch-wrap">
                                                    <?php echo maspik_toggle_button('maspik_support_custom_forms', 'maspik_support_custom_forms', 'maspik_support_custom_forms', 'maspik-form-switch togglebutton', "form-toggle", maspik_get_settings( "maspik_support_custom_forms", 'form-toggle' )); ?>
                                                        <div class="wp-custom-form">
                                                                <h4> <?php esc_html_e('Support Custom PHP Forms', 'contact-forms-anti-spam'); ?></h4>
                                                                <span><?php esc_html_e('For developers - ', 'contact-forms-anti-spam'); ?><a href="https://wpmaspik.com/documentation/custom-form-integration/" target="_blank"><?php esc_html_e('Documentation', 'contact-forms-anti-spam'); ?></a></span>
                                                        </div>  
                                                </div><!-- end of maspik-custom-switch-wrap-->


                                                <div class="forms-pro-block <?php echo esc_attr(maspik_add_pro_class()) ?>" >

                                                <?php if ( !cfes_is_supporting("ip_verification") ) { ?>
                                                    <p style="font-size: 16px;margin-bottom: 0;"><?php esc_html_e('The following forms are supported in Maspik Pro version only:', 'contact-forms-anti-spam'); ?></p>
                                                <?php } ?>
                                                <div class="pro-btn-wrapper <?php echo esc_attr(maspik_add_pro_class()) ?>"><?php maspik_get_pro() ?></div>   
                                                <div class="maspik-gravity-form-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('gravityforms') == 1 ? 'enabled':'disabled' ?>">
                                                        <?php echo maspik_toggle_button('maspik_support_gravity_forms', 'maspik_support_gravity_forms', 'maspik_support_gravity_forms', 'maspik-form-switch togglebutton', "form-toggle", 
                                                        (efas_if_plugin_is_active('gravityforms') && maspik_proform_togglecheck('Gravityforms')) == 1 ); ?>
                                                            <div>
                                                                <h4> <?php esc_html_e('Support Gravity Forms', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-gravity-form-switch-wrap -->

                                                    <div class="maspik-wpforms-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('wpforms') == 1 ? 'enabled':'disabled' ?>">
                                                        <?php echo maspik_toggle_button('maspik_support_Wpforms', 'maspik_support_Wpforms', 'maspik_support_Wpforms', 'maspik-form-switch togglebutton', "form-toggle", (efas_if_plugin_is_active('Wpforms') && maspik_proform_togglecheck('Wpforms')) == 1 ); 
                                                        
                                                        ?>
                                                            <div>
                                                                <h4> <?php esc_html_e('Support WPforms', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-wpforms-switch-wrap -->

                                                    <div class="maspik-woo-review-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('woocommerce') == 1 ? 'enabled':'disabled' ?>">
                                                        <?php echo maspik_toggle_button('maspik_support_woocommerce_review', 'maspik_support_woocommerce_review', 'maspik_support_woocommerce_review', 'maspik-form-switch togglebutton', "form-toggle", 
                                                        (efas_if_plugin_is_active('woocommerce') && maspik_proform_togglecheck('Woocommerce Review')) == 1 ); 
                                                        ?>
                                                            <div>
                                                                <h4> <?php esc_html_e('Support Woocommerce Review', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-woo-review-switch-wrap -->

                                                    <div class="maspik-woo-registration-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('woocommerce') == 1 ? 'enabled':'disabled' ?>">
                                                        <?php echo maspik_toggle_button('maspik_support_Woocommerce_registration', 'maspik_support_Woocommerce_registration', 'maspik_support_Woocommerce_registration', 'maspik-form-switch togglebutton', "form-toggle", 
                                                        (efas_if_plugin_is_active('woocommerce') && maspik_proform_togglecheck('Woocommerce Registration')) == 1 ); ?>
                                                            <div>
                                                                <h4> <?php esc_html_e('Support Woocommerce Registration', 'contact-forms-anti-spam'); ?> </h4>
                                                        </div>  
                                                    </div><!-- end of maspik-woo-registration-switch-wrap -->

                                                    <div class="maspik-woo-orders-switch-wrap togglewrap maspik-form-switch-wrap <?php echo efas_if_plugin_is_active('woocommerce') == 1 ? 'enabled':'disabled' ?>">
                                                        <?php echo maspik_toggle_button('maspik_support_woocommerce_orders', 'maspik_support_woocommerce_orders', 'maspik_support_woocommerce_orders', 'maspik-form-switch togglebutton', "form-toggle",
                                                        (efas_if_plugin_is_active('woocommerce') && maspik_proform_togglecheck('Woocommerce Orders')) == 1 ); ?>
                                                            <div>
                                                                <h4> <?php esc_html_e('Support Woocommerce Orders (checkout)', 'contact-forms-anti-spam'); ?> </h4>
                                                                <p class="description"><?php esc_html_e('Pro only. Off by default. You choose which payment gateways to check (whitelist). After enabling and saving, refresh this page to see the gateway settings accordion below.', 'contact-forms-anti-spam'); ?></p>
                                                        </div>
                                                    </div><!-- end of maspik-woo-orders-switch-wrap -->

                                                </div>
                                            
                                                <?php 
                                                    maspik_save_button_show() ?>
                                                
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Accordion Item - Other Options Field - Custom -->
                                    <div class="maspik-accordion-item maspik-accordion-other-option-field" >
                                        <div class="maspik-accordion-header" id="spam-log-accordion">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e('Spam Log and Validation message', 'contact-forms-anti-spam'); ?></h4><!--Accordion Title-->
                                            </div>
                                            <div class = "maspik-pro-button-wrap">
                                                <span class="maspik-acc-arrow">
                                                    <span class="dashicons dashicons-arrow-right"></span>
                                                </span>
                                            </div>
                                        </div>
                                            
                                        <div class="maspik-accordion-content" id="maspik-form-options">
                                            <div class="maspik-accordion-content-wrap hide-form-title">
                                                    
                                                <div class="maspik-accordion-subtitle-wrap add-space-top short-tooltip">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e('Default validation error message', 'contact-forms-anti-spam'); ?></h3>
                                                    <?php 
                                                        maspik_tooltip(esc_html__("This is the error message that the user/spammer will receive.", 'contact-forms-anti-spam'));
                                                    ?>
                                                </div> <!--end of maspik-accordion-subtitle-wrap-->

                                                <div class="maspik-general-custom-msg-wrap maspik-main-list-wrap maspik-textfield-list">

                                                    <?php
                                                        echo create_maspik_textarea('error_message', 2, 80, 'maspik-textarea', 'error-message');
                                                    ?>  

                                                </div> <!-- end of maspik-general-custom-msg-wrap  -->
                                                <?php maspik_spam_api_list('error_message'); ?>

                                                <!---- Language section divider S---------->
                                                <div class = 'maspik-simple-divider'></div>
                                                <!---- Language section divider E---------->
                                            
                                                <div class="maspik-limit-char-head togglewrap">
                                                    <?php          
                                                        echo maspik_toggle_button('maspik_Store_log', 'maspik_Store_log', 'maspik_Store_log', 'maspik-toggle-store-log togglebutton', 'yes-no' , 1);
                                                                
                                                        echo "<h4>" . esc_html__("Spam Log", "contact-forms-anti-spam"). "</h4>";

                                                        maspik_tooltip(esc_html__("If disabled, the Log of the blocked spam will not be Saved", 'contact-forms-anti-spam'));
                                                    ?>
                                                </div>

                                                <div class="maspik-limit-char-box togglebox">
                                                    <?php 
                                                        echo create_maspik_numbox("spam_log_limit", "spam_log_limit", "spam_log_limit" , "Entry limit", "1000", "","");
                                                    ?>
                                                </div> <!-- end of spam log toggle box -->
                                                <?php 
                                                    maspik_save_button_show() ?>
                                            </div>
                                        </div><!-- end of maspik-accordion-content -->
                                    </div><!-- end of maspik-accordion-item -->

                                    <?php
                                    $show_woo_orders_accordion = efas_if_plugin_is_active( 'woocommerce' ) && maspik_get_settings( 'maspik_support_woocommerce_orders', 'form-toggle' ) === 'yes';
                                    if ( $show_woo_orders_accordion ) :
                                        $available_gateways = array();
                                        if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
                                            foreach ( WC()->payment_gateways()->payment_gateways() as $gid => $gateway ) {
                                                if ( is_object( $gateway ) ) {
                                                    $available_gateways[ $gid ] = method_exists( $gateway, 'get_title' ) ? $gateway->get_title() : ( isset( $gateway->title ) ? $gateway->title : $gid );
                                                } else {
                                                    $available_gateways[ $gid ] = $gid;
                                                }
                                            }
                                        }
                                        $saved_gateways = maspik_get_settings( 'maspik_woo_orders_gateways_to_check' );
                                        if ( ! is_array( $saved_gateways ) ) {
                                            $saved_gateways = is_string( $saved_gateways ) ? ( json_decode( $saved_gateways, true ) ?: array() ) : array();
                                        }
                                        $woo_orders_msg = maspik_get_settings( 'maspik_woo_orders_error_message' );
                                        $woo_orders_msg = is_string( $woo_orders_msg ) ? $woo_orders_msg : '';
                                        $woo_orders_zero_raw = maspik_get_settings( 'maspik_woo_orders_check_zero_total' );
                                        $woo_orders_zero = ( $woo_orders_zero_raw === 'yes' || $woo_orders_zero_raw === '' || $woo_orders_zero_raw === null );
                                    ?>
                                    <!-- Accordion Item - WooCommerce Orders: gateways & message (visible only when WooCommerce Orders is enabled, after refresh) -->
                                    <div class="maspik-accordion-item maspik-accordion-woo-orders-field">
                                        <input type="hidden" name="maspik_woo_orders_accordion_visible" value="1" />
                                        <div class="maspik-accordion-header">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text"><?php esc_html_e( 'WooCommerce Orders – Gateways & message', 'contact-forms-anti-spam' ); ?></h4>
                                            </div>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                        </div>
                                        <div class="maspik-accordion-content">
                                            <div class="maspik-accordion-content-wrap hide-form-title">
                                                <div class="maspik-woo-orders-intro" style="margin-bottom: 1.25em; padding: 12px 14px; border-left: 4px solid #2271b1; background: #f0f6fc;">
                                                    <strong><?php esc_html_e( 'When does the spam check run?', 'contact-forms-anti-spam' ); ?></strong>
                                                    <p style="margin: 8px 0 0 0; font-size: 13px; color: #50575e;"><?php esc_html_e( 'The spam check runs if at least one of the following is true:', 'contact-forms-anti-spam' ); ?></p>
                                                    <ul style="margin: 8px 0 0 0; padding-left: 1.25em; font-size: 13px; color: #50575e;">
                                                        <li><?php esc_html_e( 'The payment method used is in your selected gateways list.', 'contact-forms-anti-spam' ); ?></li>
                                                        <li><?php esc_html_e( 'The order total is 0 and zero-total checking is enabled.', 'contact-forms-anti-spam' ); ?></li>
                                                    </ul>
                                                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #50575e;"><?php esc_html_e( 'If no gateways are selected and zero-total checking is disabled, checkout will not be validated.', 'contact-forms-anti-spam' ); ?></p>
                                                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #50575e;"><strong><?php esc_html_e( 'Note:', 'contact-forms-anti-spam' ); ?></strong> <?php esc_html_e( 'This spam check works only on classic checkout (shortcode) or regular checkout, not on block checkout.', 'contact-forms-anti-spam' ); ?></p>
                                                </div>

                                                <!-- 1. Payment gateways select -->
                                                <div class="maspik-accordion-subtitle-wrap add-space-top short-tooltip">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e( 'Payment gateways to check', 'contact-forms-anti-spam' ); ?></h3>
                                                    <?php maspik_tooltip( esc_html__( 'Select the payment methods that should trigger a spam check. Any checkout using one of these gateways will be validated.', 'contact-forms-anti-spam' ) ); ?>
                                                </div>
                                                <div class="maspik-woo-orders-gateways-wrap maspik-main-list-wrap">
                                                    <?php if ( ! empty( $available_gateways ) ) : ?>
                                                        <select name="maspik_woo_orders_gateways_to_check[]" id="maspik_woo_orders_gateways_to_check" class="maspik-select maspik-woo-gateways-select" multiple="multiple" style="min-width: 280px; min-height: 120px;">
                                                            <?php foreach ( $available_gateways as $gid => $label ) : ?>
                                                                <option value="<?php echo esc_attr( $gid ); ?>" <?php selected( in_array( $gid, $saved_gateways, true ) ); ?>><?php echo esc_html( $label ); ?> (<?php echo esc_html( $gid ); ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <p class="description"><?php esc_html_e( 'No WooCommerce payment gateways found.', 'contact-forms-anti-spam' ); ?></p>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="maspik-simple-divider"></div>

                                                <!-- 2. Zero total: simple toggle -->
                                                <div class="maspik-limit-char-head togglewrap">
                                                    <?php
                                                    echo maspik_toggle_button( 'maspik_woo_orders_check_zero_total', 'maspik_woo_orders_check_zero_total', 'maspik_woo_orders_check_zero_total', 'maspik-toggle togglebutton', 'yes-no', $woo_orders_zero ? 1 : 0 );
                                                    ?>
                                                    <h4><?php esc_html_e( 'Check free orders (total = 0)', 'contact-forms-anti-spam' ); ?></h4>
                                                    <?php maspik_tooltip( esc_html__( 'Enable spam protection for orders that do not require payment.', 'contact-forms-anti-spam' ) ); ?>
                                                </div>

                                                <div class="maspik-simple-divider"></div>

                                                <!-- 3. Error / notice message -->
                                                <div class="maspik-accordion-subtitle-wrap add-space-top short-tooltip">
                                                    <h3 class="maspik-accordion-subtitle"><?php esc_html_e( 'Message to show when checkout is blocked (notice / error)', 'contact-forms-anti-spam' ); ?></h3>
                                                    <?php maspik_tooltip( esc_html__( 'Leave empty to use the default validation error message from the Spam Log section above.', 'contact-forms-anti-spam' ) ); ?>
                                                </div>
                                                <div class="maspik-general-custom-msg-wrap maspik-main-list-wrap maspik-textfield-list">
                                                    <textarea name="maspik_woo_orders_error_message" id="maspik_woo_orders_error_message" class="maspik-textarea" rows="2" cols="80" placeholder="<?php esc_attr_e( 'e.g. We could not process your order. Please try again or contact us.', 'contact-forms-anti-spam' ); ?>"><?php echo esc_textarea( $woo_orders_msg ); ?></textarea>
                                                </div>

                                                <?php maspik_save_button_show(); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Plugin Settings Accordion -->
                                    <div class="maspik-accordion-item maspik-accordion-plugin-settings">
                                        <div class="maspik-accordion-header">
                                            <div class="mpk-acc-header-texts">
                                                <h4 class="maspik-header maspik-accordion-header-text">
                                                    <?php esc_html_e('Plugin Settings', 'contact-forms-anti-spam'); ?>
                                                </h4>
                                            </div>
                                            <span class="maspik-acc-arrow">
                                                <span class="dashicons dashicons-arrow-right"></span>
                                            </span>
                                        </div>
                                        
                                        <div class="maspik-accordion-content">
                                            <div class="maspik-accordion-content-wrap hide-form-title">


                                                <div class="maspik-shere-data-switch-wrap togglewrap maspik-more-options-switch-wrap">
                                                    <?php 
                                                        echo maspik_toggle_button('shere_data', 'shere_data', 'shere_data', 'maspik-more-options-switch togglebutton' );
                                                        echo "<h4>". esc_html__("Share anonymous data to improve spam protection", "contact-forms-anti-spam"). "</h4>";
                                                        maspik_tooltip(esc_html__("By allowing us to track usage data, we can better help you by knowing which WordPress configurations, themes, and plugins to test and which options are needed.", "contact-forms-anti-spam") );
                                                    ?>
                                                </div><!-- end of mmaspik-add-country-switch-wrap -->

                                                <!---- Settings section divider -->
                                                <?php 
                                                /*
                                                <div class="maspik-simple-divider"></div>

                                                <!-- Load Template Settings -->
                                                <div class="maspik-settings-action-wrap">
                                                    <div class="maspik-settings-action-content">
                                                        <h4><?php esc_html_e('Load Industry Template', 'contact-forms-anti-spam'); ?></h4>
                                                        <span><?php esc_html_e('Load pre-configured settings optimized for your website type.', 'contact-forms-anti-spam'); ?></span>
                                                        
                                                        <div class="template-selection-wrap">
                                                            <select name="website_type" id="website_type" class="maspik-select">
                                                                <option value=""><?php esc_html_e('Select Website Type', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="ecommerce"><?php esc_html_e('E-Commerce Store', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="blog"><?php esc_html_e('Blog / Portfolio / Services', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="seo"><?php esc_html_e('SEO Agency / Marketing / Web Agency', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="onlyusa"><?php esc_html_e('Only USA based customers (For pro users)', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="onlyeu"><?php esc_html_e('Only EU based customers (For pro users)', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="onlychina"><?php esc_html_e('Only China based customers (For pro users)', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="latinlangneeded"><?php esc_html_e('Only Latin language customers (For pro users)', 'contact-forms-anti-spam'); ?></option>
                                                                <option value="general"><?php esc_html_e('General', 'contact-forms-anti-spam'); ?></option>
                                                            </select>
                                                            <p class="template-description"></p>
                                                        </div>
                                                    </div>
                                                    <button type="button" id="maspik-load-template" class="button maspik-action-button" disabled>
                                                        <span class="dashicons dashicons-download"></span>
                                                        <?php esc_html_e('Load Template', 'contact-forms-anti-spam'); ?>
                                                    </button>
                                                </div>
                                                */
                                                ?>
                                                <!---- Settings section divider -->
                                                <div class="maspik-simple-divider"></div>

                                                <!-- Reset Plugin Settings -->
                                                <div class="maspik-settings-action-wrap">
                                                    <div class="maspik-settings-action-content">
                                                        <h4><?php esc_html_e('Reset Plugin Settings', 'contact-forms-anti-spam'); ?></h4>
                                                        <span><?php esc_html_e('Reset all plugin settings to their default values. This action cannot be undone.', 'contact-forms-anti-spam'); ?></span>
                                                    </div>
                                                    <button type="button" id="maspik-reset-settings" class="button maspik-action-button">
                                                        <span class="dashicons dashicons-image-rotate"></span>
                                                        <?php esc_html_e('Reset Settings', 'contact-forms-anti-spam'); ?>
                                                    </button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <style>

                                    .maspik-settings-action-content {
                                        margin-bottom: 15px;
                                    }

                                    .maspik-settings-action-content h4 {
                                        margin: 0 0 10px 0;
                                        font-size: 16px;
                                    }

                                    .template-selection-wrap {
                                        margin-top: 15px;
                                    }

                                    .maspik-action-button {
                                        display: flex !important;
                                        align-items: center;
                                        gap: 5px;
                                        padding: 8px 15px !important;
                                        background-color: #f0f0f1 !important;
                                        color: #2c3338 !important;
                                        border-color: #2c3338 !important;
                                        transition: all 0.3s ease !important;
                                    }

                                    .maspik-action-button:hover {
                                        background-color: #2c3338 !important;
                                        color: #fff !important;
                                    }

                                    .maspik-action-button:disabled {
                                        opacity: 0.6;
                                        cursor: not-allowed;
                                    }

                                    .template-description {
                                        margin-top: 10px;
                                        font-style: italic;
                                        color: #666;
                                    }

                                    #website_type {
                                        width: 100%;
                                        max-width: 400px;
                                    }
                                    </style>

                                    <script>
                                    jQuery(document).ready(function($) {
                                        // Enable/disable load template button based on selection
                                        $('#website_type').on('change', function() {
                                            var selectedValue = $(this).val();
                                            $('#maspik-load-template').prop('disabled', !selectedValue);
                                            
                                            var descriptions = {
                                                'blog': '<?php echo esc_js(MASPIK_TEMPLATES["blog"]["description"]); ?>',
                                            };
                                            
                                            // Update description text
                                            $('.template-description').text(descriptions[selectedValue] || '');
                                        });

                                        // Reset settings action
                                        $('#maspik-reset-settings').on('click', function() {
                                            var $button = $(this);
                                            
                                            if ($button.data('maspikResetting')) {
                                                return;
                                            }

                                            if (confirm('<?php esc_html_e('Are you sure you want to reset all settings? This action cannot be undone.', 'contact-forms-anti-spam'); ?>')) {
                                                $button.data('maspikResetting', true);
                                                maspik_reset_settings();
                                            }
                                        });
                                        function maspik_reset_settings() {
                                            $.ajax({
                                                url: ajaxurl,
                                                type: 'POST',
                                                data: {
                                                    action: 'maspik_reset_settings',
                                                    nonce: $('#maspik_save_settings_nonce').val()
                                                },
                                                beforeSend: function() {
                                                    // Show loading state
                                                    $('#maspik-reset-settings')
                                                        .prop('disabled', true)
                                                        .html('<span class="dashicons dashicons-update-alt spinning"></span> Resetting...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        // Show success message
                                                        alert('Settings reset successfully. The page will now reload.');
                                                        location.reload();
                                                    } else {
                                                        // Show error message
                                                        alert('Failed to reset settings: ' + (response.data.message || 'Unknown error'));
                                                        console.error('Reset failed:', response.data);
                                                    }
                                                },
                                                error: function(xhr, status, error) {
                                                    // Show error message
                                                    alert('Failed to reset settings. Please try again.');
                                                    console.error('Ajax error:', status, error);
                                                },
                                                complete: function() {
                                                    // Reset button state
                                                    $('#maspik-reset-settings')
                                                        .data('maspikResetting', false)
                                                        .prop('disabled', false)
                                                        .html('<span class="dashicons dashicons-image-rotate"></span> Reset Settings');
                                                }
                                            });
                                        }

                                    });
                                    </script>
                                    <!-- END of plugin settings accordion -->
                                    

                                    <?php wp_nonce_field('maspik_save_settings_action', 'maspik_save_settings_nonce'); ?>
                                <script>
                                (function () {
                                    var form = document.querySelector('form.maspik-form');
                                    if (!form) {
                                        return;
                                    }
                                    var triggerNames = { 'maspik-save-btn': true, 'maspik-api-save-btn': true, 'maspik-api-refresh-btn': true };
                                    form.addEventListener('submit', function (e) {
                                        var sub = e.submitter;
                                        if (!sub || !sub.name) {
                                            sub = document.activeElement;
                                        }
                                        if (!sub || !triggerNames[sub.name]) {
                                            return;
                                        }
                                        var wrap = sub.closest('.maspik-save-submit-wrap');
                                        if (!wrap) {
                                            return;
                                        }
                                        wrap.classList.add('maspik-save-is-busy');
                                        form.setAttribute('aria-busy', 'true');
                                    });
                                })();
                                </script>
                                </form> <!--End of content to submit -->


                                <!--  -->
                                <!-- Feedback Form Section -->
                                <div class="maspik-feedback-section" id="maspik-feedback">
                                    <div class="maspik-settings-action-content" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <span class="dashicons dashicons-format-chat" style="font-size: 28px; color: #F48722;"></span>
                                        <div>
                                            <h4 style="margin: 0; padding: 0; font-size: 18px; color: #2c3338; display: inline-block; vertical-align: middle;">
                                                <?php esc_html_e('Send Feedback', 'contact-forms-anti-spam'); ?>
                                            </h4>
                                            <div style="font-size: 13px; color: #666; margin-top: 2px;">
                                                <?php esc_html_e('Help us improve Maspik by sharing your thoughts and suggestions.', 'contact-forms-anti-spam'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <form id="maspik-feedback-form" class="maspik-feedback-form">
                                        <div class="maspik-feedback-field">
                                            <label for="maspik-feedback-type"><?php esc_html_e('Feedback Type:', 'contact-forms-anti-spam'); ?></label>
                                            <select id="maspik-feedback-type" name="feedback_type" required>
                                                <option value="suggestion"><?php esc_html_e('Suggestion', 'contact-forms-anti-spam'); ?></option>
                                                <option value="bug"><?php esc_html_e('Bug Report', 'contact-forms-anti-spam'); ?></option>
                                                <option value="feature"><?php esc_html_e('Feature Request', 'contact-forms-anti-spam'); ?></option>
                                                <option value="other"><?php esc_html_e('Other', 'contact-forms-anti-spam'); ?></option>
                                            </select>
                                        </div>
                                        <div class="maspik-feedback-field">
                                            <label for="maspik-feedback-message"><?php esc_html_e('Your Message:', 'contact-forms-anti-spam'); ?></label>
                                            <textarea id="maspik-feedback-message" name="feedback_message" rows="4" required></textarea>
                                        </div>
                                        <div class="maspik-feedback-field">
                                            <label for="maspik-feedback-email"><?php esc_html_e('Your Email - If expecting answer (optional):', 'contact-forms-anti-spam'); ?></label>
                                            <input type="email" id="maspik-feedback-email" name="feedback_email">
                                        </div>
                                        <button type="submit" class="button maspik-feedback-btn">
                                            <span class="dashicons dashicons-email-alt"></span>
                                            <?php esc_html_e('Send Feedback', 'contact-forms-anti-spam'); ?>
                                        </button>
                                        <div id="maspik-feedback-message-status" style="display:none; margin-top:10px;"></div>
                                    </form>
                                </div>

                                <style>
                                .maspik-feedback-section {
                                    padding: 28px 28px 20px 28px;
                                    background: #fff7f0;
                                    border-radius: 12px;
                                    box-shadow: 0 2px 10px 0 rgba(244,135,34,0.07);
                                }
                                .maspik-feedback-form {
                                    margin-top: 10px;
                                }
                                .maspik-feedback-field {
                                    margin-bottom: 18px;
                                }
                                .maspik-feedback-field label {
                                    display: block;
                                    margin-bottom: 6px;
                                    font-weight: 600;
                                    color: #2c3338;
                                }
                                .maspik-feedback-field select,
                                .maspik-feedback-field textarea,
                                .maspik-feedback-field input {
                                    width: 100%;
                                    padding: 9px 12px;
                                    border: 1.5px solid #e2e4e7;
                                    border-radius: 7px;
                                    background: #fff;
                                    font-size: 15px;
                                    transition: border-color 0.2s, box-shadow 0.2s;
                                    box-sizing: border-box;
                                    max-width: 100%;
                                }
                                .maspik-feedback-field select:focus,
                                .maspik-feedback-field textarea:focus,
                                .maspik-feedback-field input:focus {
                                    border-color: #F48722;
                                    outline: none;
                                    box-shadow: 0 0 0 2px #ffe3c7;
                                }
                                .maspik-feedback-field textarea {
                                    resize: vertical;
                                    min-height: 80px;
                                }
                                .button.maspik-feedback-btn {
                                    background: #F48722;
                                    border-color: #F48722;
                                    color: #fff;
                                    font-weight: 600;
                                    border-radius: 7px;
                                    padding: 10px 22px;
                                    font-size: 16px;
                                    transition: background 0.2s, border-color 0.2s;
                                    box-shadow: 0 1px 4px 0 rgba(244,135,34,0.08);
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 7px;
                                }
                                .button.maspik-feedback-btn:hover {
                                    background: #e06f0f;
                                    border-color: #e06f0f;
                                    color: #fff;
                                }
                                #maspik-feedback-message-status {
                                    font-size: 15px;
                                    padding: 8px 12px;
                                    border-radius: 6px;
                                    margin-top: 10px;
                                }
                                #maspik-feedback-message-status.success {
                                    background: #e6f7e6;
                                    color: #1a7f37;
                                    border: 1px solid #b6e2b6;
                                }
                                #maspik-feedback-message-status.error {
                                    background: #fff0f0;
                                    color: #c00;
                                    border: 1px solid #f5b6b6;
                                }
                                </style>

                                <script>
                                jQuery(document).ready(function($) {
                                    $('#maspik-feedback-form').on('submit', function(e) {
                                        e.preventDefault();
                                        var formData = {
                                            action: 'maspik_submit_feedback',
                                            nonce: '<?php echo wp_create_nonce('maspik_feedback_nonce'); ?>',
                                            feedback_type: $('#maspik-feedback-type').val(),
                                            feedback_message: $('#maspik-feedback-message').val(),
                                            feedback_email: $('#maspik-feedback-email').val()
                                        };
                                        var statusDiv = $('#maspik-feedback-message-status');
                                        statusDiv.hide().removeClass('success error');
                                        $.post(ajaxurl, formData, function(response) {
                                            if (response.success) {
                                                statusDiv.text('<?php esc_html_e('Thank you for your feedback!', 'contact-forms-anti-spam'); ?>').addClass('success').show();
                                                $('#maspik-feedback-form')[0].reset();
                                            } else {
                                                statusDiv.text('<?php esc_html_e('There was an error sending your feedback. Please try again.', 'contact-forms-anti-spam'); ?>').addClass('error').show();
                                            }
                                        });
                                    });
                                });
                                </script>
                                <!-- end feedback-->
                            </div><!-- End of Accordion content -->
                        </div><!--end of blacklist opts -->
                    </div><!-- end of Xmaspik-blacklist-options -->

                    <!--Test form here -->
                    <div class="maspik-test-form form-container test-form">
                            <form name="frmContact"  id="frmContact" method="post"  enctype="multipart/form-data">
                                <div class="maspik-test-form-buttons">
                                    <button data-id="contact-form" type="button"><?php esc_html_e('Contact form', 'contact-forms-anti-spam'); ?></button>
                                    <button data-id="registration" type="button"><?php esc_html_e('Registration', 'contact-forms-anti-spam'); ?></button>
                                    <button data-id="comment" type="button"><?php esc_html_e('Comment', 'contact-forms-anti-spam'); ?></button>
                                </div>
                                    <h3 class="maspik-test-form-head maspik-header"><?php esc_html_e('Playground - Form example', 'contact-forms-anti-spam'); ?></h3>    
                                    <p  class="maspik-test-form-sub"><?php esc_html_e('This form allows you to test your entries to see if they will be blocked.', 'contact-forms-anti-spam'); ?>
                                <div class="input-row row-text">
                                    <label><?php esc_html_e('Name (Text field)', 'contact-forms-anti-spam'); ?></label> <span
                                        id="userName-info" class="info"></span> <input type="text"
                                        class="input-field" name="userName" id="userName" />
                                    <span class="note" id="note-name"></span>
                                </div>
                                <div class="input-row row-email">
                                    <label><?php esc_html_e('Email (Email field)', 'contact-forms-anti-spam'); ?></label> <span id="userEmail-info" class="info"></span>
                                    <input type="email" class="input-field" name="userEmail"
                                        id="userEmail" />
                                    <span class="note" id="note-email"></span>
                                </div>
                                <div class="input-row row-phone">
                                    <label><?php esc_html_e('Phone (Phone field)', 'contact-forms-anti-spam'); ?></label> <span id="subject-info" class="info"></span>
                                    <input type="tel" class="input-field" name="tel" id="tel" />
                                    <span class="note" id="note-tel"></span>
                                </div>
                                <div class="input-row row-url" style="display: none;">
                                    <label><?php esc_html_e('Website (URL field)', 'contact-forms-anti-spam'); ?></label> <span id="subject-info" class="info"></span>
                                    <input type="url" class="input-field" name="url" id="url" />
                                    <span class="note" id="note-url"></span>
                                </div>
                                <div class="input-row row-content">
                                    <label><?php esc_html_e('Message (Text area field)', 'contact-forms-anti-spam'); ?></label> <span id="userMessage-info" class="info"></span>
                                    <textarea name="content" id="content" class="input-field" cols="60"
                                        rows="2"></textarea>
                                    <span class="note" id="note-textarea"></span>
                                </div>
                                <div>
                                    <input type="submit" name="send" class="btn-submit maspik-btn" value="<?php esc_attr_e('Check', 'contact-forms-anti-spam'); ?>" />
                                </div>
                                <br><strong><?php esc_html_e('* Please save changes before checking.', 'contact-forms-anti-spam'); ?></strong>
                                <br><div id="statusMessage"></div>
                            </form>
                        </div>
                    <!-- end test form -->


                </div><!-- end of maspik-setting-body -->
                    <?php echo get_maspik_footer(); ?>
                </div> <!-- end of maspik-settings -->
                  
                <div class="forms-warp">
                    <div id="popup-background"></div>        
                </div><!-- end forms warp -->

                <div id="pop-up-example" class="maspik-popup-wrap">
                    <h3 class="maspik-popup-title-wrap"><?php esc_html_e('Example for', 'contact-forms-anti-spam'); ?> <span class="maspik-popup-title"><?php esc_html_e('Text field', 'contact-forms-anti-spam'); ?></span></h3>
                    <p class="pop-up-subtext"><?php esc_html_e('Here you can see an example options for the', 'contact-forms-anti-spam'); ?>  <span class="maspik-popup-title"><?php esc_html_e('text field', 'contact-forms-anti-spam'); ?></span></p>
                    <button class="close-popup"><span class="dashicons dashicons-no-alt"></span></button>
                        <div class="data-array-wrap">
                            <div class="data-array-here maspik-custom-scroll">
                                <ul>
                                <!-- Example words will be dynamically inserted here -->
                                </ul>
                            </div>
                        </div>
                    <div class="maspik-copy-btn-wrap">
                        <button class="copy maspik-btn"><?php esc_html_e('Copy list', 'contact-forms-anti-spam'); ?></button>
                    </div>
                    <div id="copy-message" style="display: none;"><?php esc_html_e('List copied!', 'contact-forms-anti-spam'); ?></div>

                </div>

                <div id="pop-up-shortcode" class="maspik-popup-wrap">
                    <h3 class="maspik-popup-title-wrap"><span class="maspik-popup-title"><?php esc_html_e('Shortcode List', 'contact-forms-anti-spam'); ?></span></h3>
                    <p class="pop-up-subtext"><?php esc_html_e('You can also use the following shortcodes:', 'contact-forms-anti-spam'); ?></p>
                    <button class="close-popup"><span class="dashicons dashicons-no-alt"></span></button>
                    <div class="data-array-wrap">
                        <div class="data-array-here maspik-custom-scroll">
                            <ul>
                            <!-- Example words will be dynamically inserted here -->
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="pop-up-ip-verification" class="maspik-popup-wrap" style="width: 700px; max-width: 90%; overflow: scroll; max-height: 60vh;">
                    <button class="close-popup"><span class="dashicons dashicons-no-alt"></span></button>
                    <?php echo IP_Verification_popup_content(); ?>
                </div>

                <div id="pop-up-whats-new" class="maspik-popup-wrap maspik-whats-new-popup">
                    <button class="maspik-whats-new-close close-popup" type="button" aria-label="<?php esc_attr_e('Close', 'contact-forms-anti-spam'); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                    <div class="maspik-whats-new-inner">
                        <h2 class="maspik-whats-new-title">
                            <span class="maspik-whats-new-icon dashicons dashicons-megaphone" aria-hidden="true"></span>
                            <?php esc_html_e("What's New in Maspik", 'contact-forms-anti-spam'); ?>
                        </h2>

                        <section class="maspik-whats-new-topic">
                            <h3 class="maspik-whats-new-topic-title"><?php esc_html_e('Unified Blacklist Fields', 'contact-forms-anti-spam'); ?></h3>
                            <p><?php esc_html_e("We've merged the Text and Textarea blacklist into one place. You now manage blocked words for both fields from a single setting.", 'contact-forms-anti-spam'); ?></p>
                        </section>

                        <section class="maspik-whats-new-topic">
                            <h3 class="maspik-whats-new-topic-title"><?php esc_html_e('Maspik Matrix', 'contact-forms-anti-spam'); ?></h3>
                            <p><?php esc_html_e('Maspik Matrix helps detect more complex and borderline spam. You can enable it from the dashboard widget or in settings.', 'contact-forms-anti-spam'); ?></p>
                        </section>

                        <section class="maspik-whats-new-tips">
                            <h3 class="maspik-whats-new-tips-title"><?php esc_html_e('Tips to Improve Spam Protection', 'contact-forms-anti-spam'); ?></h3>
                            <div class="maspik-whats-new-tip">
                                <h4 class="maspik-whats-new-tip-heading"><?php esc_html_e('Review Your Spam Log', 'contact-forms-anti-spam'); ?></h4>
                                <p><?php esc_html_e('Identify recurring patterns and false positives.', 'contact-forms-anti-spam'); ?></p>
                            </div>
                            <div class="maspik-whats-new-tip">
                                <h4 class="maspik-whats-new-tip-heading"><?php esc_html_e('Add Recurring Spam Words', 'contact-forms-anti-spam'); ?></h4>
                                <p><?php esc_html_e('Block repeated phrases to stop them automatically next time.', 'contact-forms-anti-spam'); ?></p>
                            </div>
                        </section>

                        <p class="maspik-whats-new-footer"><?php echo wp_kses(sprintf(__('We\'d love your feedback %s', 'contact-forms-anti-spam'), '<a href="mailto:hello@wpMaspik.com">hello@wpmaspik.com</a>'), array('a' => array('href' => array()))); ?></p>
                        <div class="maspik-whats-new-actions">
                            <button type="button" class="button button-primary maspik-whats-new-gotit"><?php esc_html_e('Review settings', 'contact-forms-anti-spam'); ?></button>
                        </div>
                    </div>
                </div>

                <?php
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);
    ?>


    <script>
    var maspikWhatsNew = {
        showAuto: <?php echo $show_whats_new_auto ? 'true' : 'false'; ?>,
        version: '<?php echo esc_js($whats_new_version); ?>',
        nonce: '<?php echo esc_js($whats_new_nonce); ?>',
        ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>'
    };

    // Test form buttons
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelector('.maspik-test-form-buttons').children;
        const phoneField = document.querySelector('.row-phone');
        const urlField = document.querySelector('.row-url');
        const messageField = document.querySelector('.row-content');

        // Set Contact Form as default active
        buttons[0].classList.add('active');

        Array.from(buttons).forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                Array.from(buttons).forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');

                // Show all fields first
                phoneField.style.display = 'block';
                messageField.style.display = 'block';
                urlField.style.display = 'none';
                // Handle different form types
                switch(this.dataset.id) {
                    case 'registration':
                        phoneField.style.display = 'none';
                        messageField.style.display = 'none';
                        break;
                    case 'comment':
                        phoneField.style.display = 'none';
                        urlField.style.display = 'block';
                        break;
                    // contact-form shows everything by default
                }
            });
        });
    });


    //Accordion JS code
 
            var acc = document.getElementsByClassName("maspik-accordion-header");
            var i;
            for (i = 0; i < acc.length; i++) {
                acc[i].addEventListener("click", function() {
                    this.classList.toggle("active");
                    var panel = this.nextElementSibling;
                    if (panel.style.maxHeight) {
                        panel.style.maxHeight = null;
                    } else {
                        panel.style.maxHeight = panel.scrollHeight + 'px';
                    }
                
                });
            }


        var formacc = document.getElementsByClassName("form-opt-toggle");
        var target = document.getElementById("form-option-accordion");
        var transitionDuration = 200;


            for (i = 0; i < formacc.length; i++) {
                formacc[i].addEventListener("click", function() {
                    if (target && !target.classList.contains("active")) {
                        target.classList.add("active");
                        var panel = target.nextElementSibling;
                        if (panel.style.maxHeight) {
                            panel.style.maxHeight = null;
                        }else {
                        panel.style.maxHeight = panel.scrollHeight + 'px';
                        }
                        
                    }

                    setTimeout(function() {
                        target.scrollIntoView({ behavior: "smooth" });
                    }, transitionDuration);
                
                });
            }

            
        

    //Accordion JS code END

    // Hide - Show on Toggle

        var checkboxes = document.querySelectorAll('.togglebutton');
        
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {

            var nextDiv = this.closest('div').nextElementSibling;
        
                while (nextDiv) {
                    if (nextDiv.classList.contains('togglebox')) {
                        if (this.checked) {
                            nextDiv.classList.add('showontoggle');
                        } else {
                            nextDiv.classList.remove('showontoggle');
                        }
                        break;
                    }
                    nextDiv = nextDiv.nextElementSibling;
                }
            });
        });


        document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('.togglebutton');
        
            checkboxes.forEach(function(checkbox) {
                
            var nextDiv = checkbox.closest('div').nextElementSibling;

            
            
        
                while (nextDiv) {
                    if (nextDiv.classList.contains('togglebox')) {
                        if (checkbox.checked) {
                            nextDiv.classList.add('showontoggle');
                        } else {
                            nextDiv.classList.remove('showontoggle');
                        }
                        break;
                    }
                    nextDiv = nextDiv.nextElementSibling;
                }
            });
        });


    // Hide - Show on Toggle - END

    jQuery(document).ready(function($) {
        jQuery('.maspik-select').select2({
            //multiple: true,
            placeholder:"<?php esc_html_e('Select', 'contact-forms-anti-spam' );?>",
            });


            function maspik_load_template() {
                const selectedTemplate = $('#website_type').val();
                // console log the selected template
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maspik_load_template',
                        template_type: selectedTemplate,
                        nonce: $('#maspik_save_settings_nonce').val()
                    },
                    beforeSend: function() {
                        // Show loading state
                        $('#maspik-load-template')
                            .prop('disabled', true)
                            .html('<span class="dashicons dashicons-update-alt spinning"></span> Loading...');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            alert('Template loaded successfully. The page will now reload.');
                            location.reload();
                        } else {
                            // Show error message
                            alert('Failed to load template: ' + (response.data.message || 'Unknown error'));
                            console.error('Template loading failed:', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        alert('Failed to load template. Please try again.');
                        console.error('Ajax error:', status, error);
                    },
                    complete: function() {
                        // Reset button state
                        $('#maspik-load-template')
                            .prop('disabled', false)
                            .html('<span class="dashicons dashicons-download"></span> Load Template');
                    }
                });
            }

            // Update the click handler
            $('#maspik-load-template').on('click', function() {
                const selectedTemplate = $('#website_type').val();

                // console log the selected template
                if (selectedTemplate && confirm('Loading a template will override your current settings. Do you want to continue?')) {
                    maspik_load_template();
                }
            });
            // Load template description
             // Enable/disable load template button based on selection
            /*$('#website_type').on('change', function() {
                var selectedValue = $(this).val();
                $('#maspik-load-template').prop('disabled', !selectedValue);
                
                // Get descriptions from PHP
                var descriptions = {
                    'blog': '</?php echo esc_js(MASPIK_TEMPLATES["blog"]["description"]); ?>',
                };
                
                // Update description text
                $('.template-description').text(descriptions[selectedValue] || '');
            });*/


        // END of Load template description

        // AI Spam Check functionality
            $(function() {
                
                // Handle AI toggle button change
                $(document).on('change', '.maspik-ai-toggle-wrap input[type="checkbox"]', function() {
                    var isChecked = $(this).is(':checked');
                    var configFields = $('#maspik-ai-config-fields');
                    
                    if (isChecked) {
                        configFields.slideDown(300);
                    } else {
                        configFields.slideUp(300);
                    }
                });

                // Initialize AI config fields visibility on page load
                $(document).ready(function() {
                    var aiToggle = $('.maspik-ai-toggle-wrap input[type="checkbox"]');
                    var configFields = $('#maspik-ai-config-fields');
                    
                    if (aiToggle.is(':checked')) {
                        configFields.show();
                    } else {
                        configFields.hide();
                    }
                });

                // Handle accordion functionality for AI section
                $(document).on('click', '#ai-spam-check-accordion', function() {
                    var content = $('#maspik-ai-spam-check');
                    var arrow = $(this).find('.dashicons-arrow-right');
                    
                    if (content.hasClass('active')) {
                        content.removeClass('active').slideUp(300);
                        arrow.removeClass('rotated');
                    } else {
                        content.addClass('active').slideDown(300);
                        arrow.addClass('rotated');
                    }
                });

            });

        }); // END of jQuery(document).ready

        
        // Function to check if the "imported" parameter is present in the URL
        function checkImportedParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('imported') && urlParams.get('imported') === '1') {
                // Show alert
                alert('<?php esc_html_e('The import completed successfully.', 'contact-forms-anti-spam'); ?>');
                // Remove "imported" parameter from the URL
                urlParams.delete('imported');
                const newUrl = window.location.pathname + '?' + urlParams.toString();
                // Redirect to the new URL, effectively refreshing the page
                window.location.href = newUrl;
            }
        }

        // Call the function when the page is loaded
        window.onload = checkImportedParameter;

        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.your-button-class');
            const popups = document.querySelectorAll('.maspik-popup-wrap');
            const closeButtons = document.querySelectorAll('.close-popup');
            const popupBackground = document.getElementById('popup-background');
            const whatsNewPopup = document.getElementById('pop-up-whats-new');

            function openWhatsNewPopup() {
                if (whatsNewPopup && popupBackground) {
                    whatsNewPopup.classList.remove('maspik-whats-new-open');
                    popupBackground.style.display = 'block';
                    whatsNewPopup.classList.add('active');
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            whatsNewPopup.classList.add('maspik-whats-new-open');
                        });
                    });
                }
            }
            function markWhatsNewSeen() {
                if (typeof maspikWhatsNew === 'undefined' || !maspikWhatsNew.version) return;
                var formData = new FormData();
                formData.append('action', 'maspik_whats_new_seen');
                formData.append('nonce', maspikWhatsNew.nonce);
                formData.append('version', maspikWhatsNew.version);
                fetch(maspikWhatsNew.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).catch(function() {});
            }
            if (typeof maspikWhatsNew !== 'undefined' && maspikWhatsNew.showAuto) {
                setTimeout(openWhatsNewPopup, 2000);
            }
            var whatsNewBtn = document.getElementById('maspik-open-whats-new-btn');
            if (whatsNewBtn) {
                whatsNewBtn.addEventListener('click', openWhatsNewPopup);
            }
            var gotItBtn = document.querySelector('.maspik-whats-new-gotit');
            if (gotItBtn && whatsNewPopup && popupBackground) {
                gotItBtn.addEventListener('click', function() {
                    markWhatsNewSeen();
                    whatsNewPopup.classList.remove('active');
                    popupBackground.style.display = 'none';
                });
            }

            buttons.forEach((button, index) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault(); // Prevent default link behavior
                    const popupId = button.dataset.popupId;
                    const popup = document.getElementById(popupId);

                    if (popup) {
                        popup.classList.toggle('active');
                        popupBackground.style.display = 'block'; // Show background
                        // Update title-here spans
                    const titleHereSpans = popup.querySelectorAll('.maspik-popup-title');
                    const buttonTitle = button.dataset.title || '<?php esc_html_e('Text field', 'contact-forms-anti-spam'); ?>'; // Default value if not provided
                    titleHereSpans.forEach(span => {
                        span.innerHTML = buttonTitle;
                    });
                    
                    // Update data-array-here content if provided
                    const dataArrayElement = popup.querySelector('.data-array-here ul');
                    const dataArray = button.dataset.array;
                    if (dataArrayElement && dataArray) {
                        const dataArrayItems = dataArray.split('|');
                        dataArrayElement.innerHTML = ''; // Clear previous data
                        dataArrayItems.forEach(item => {
                            const listItem = document.createElement('li');
                            listItem.textContent = item.trim();
                            dataArrayElement.appendChild(listItem);
                        });
                    }
                    }
                });
            });

        
            closeButtons.forEach((closeButton, index) => {
                closeButton.addEventListener('click', () => {
                    const popup = closeButton.closest('.maspik-popup-wrap');
                    if (popup) {
                        if (popup.id === 'pop-up-whats-new') markWhatsNewSeen();
                        popup.classList.remove('active');
                        popupBackground.style.display = 'none'; // Hide background
                    }
                });
            });

            // Close popup when clicking outside of it
            document.addEventListener('click', (event) => {
                if (!event.target.closest('.maspik-popup-wrap') && !event.target.closest('.your-button-class') && !event.target.closest('.maspik-open-whats-new')) {
                    const activePopups = document.querySelectorAll('.maspik-popup-wrap.active');
                    activePopups.forEach(popup => {
                        if (popup.id === 'pop-up-whats-new') markWhatsNewSeen();
                        popup.classList.remove('active');
                        popupBackground.style.display = 'none'; // Hide background
                    });
                }
            });

            const copyMessage = document.getElementById('copy-message');

            // Copy list button functionality
            const copyButtons = document.querySelectorAll('.copy');
            
            copyButtons.forEach(copyButton => {
                copyButton.addEventListener('click', () => {
                    const popup = copyButton.closest('.maspik-popup-wrap');
                    const dataArrayElement = popup.querySelector('.data-array-here');
                    const listItems = dataArrayElement.querySelectorAll('li');
                    const listText = Array.from(listItems).map(li => li.textContent).join('\n');
                    
                    // Copy list text to clipboard
                    navigator.clipboard.writeText(listText)
                    .then(() => {
                            // Show the copy message
                            copyMessage.style.display = 'block';

                            // Hide the message after a short delay (e.g., 2 seconds)
                            setTimeout(() => {
                                copyMessage.style.display = 'none';
                            }, 2000);
                    })
                    .catch(err => {
                        console.error('<?php esc_html_e('Failed to copy list to clipboard: ', 'contact-forms-anti-spam'); ?>', err);
                    });
                });
            });
        });
        
        document.addEventListener('DOMContentLoaded', function () {
            const triggers = document.querySelectorAll('.custom-validation-trigger');
            
            triggers.forEach(trigger => {
                trigger.addEventListener('click', function () {
                    const box = this.parentNode.nextElementSibling;
                    box.classList.toggle('open');
                });
            });
        });

        <?php if (cfes_is_supporting("api")) { ?>
        function maspikUpdatePrivateFileId() {
            // Get ID from URL parameters safely
            const urlParams = new URLSearchParams(window.location.search);
            const newId = urlParams.get('private_file_id');
            
            // If there's no private_file_id parameter, exit early
            if (!newId) {
                return false;
            }

            // Check if we already processed this request
            if (sessionStorage.getItem('maspik_processed_id') === newId) {
                // Already processed this ID, just clean up the URL
                const newUrl = window.location.pathname + "?page=maspik";
                if (window.location.href !== newUrl) {
                    window.history.replaceState({}, '', newUrl);
                }
                return false;
            }

            // Remove private_file_id from URL
            const newUrl = window.location.pathname + "?page=maspik";

            // Ensure it's a positive number
            const numericId = parseInt(newId, 10);
            if (isNaN(numericId) || numericId <= 0) {
                window.history.replaceState({}, '', newUrl);
                return false;
            }

            // Get the input element safely
            const idInput = document.querySelector('input[name="private_file_id"]');
            if (!idInput) {
                window.history.replaceState({}, '', newUrl);
                return false;
            }

            // Get the submit button safely
            const submitButton = document.querySelector('input[name="maspik-api-save-btn"]');
            if (!submitButton) {
                window.history.replaceState({}, '', newUrl);
                return false;
            }

            try {
                // Mark this ID as processed
                sessionStorage.setItem('maspik_processed_id', newId);
                
                // Update the input value
                idInput.value = numericId;

                // Create and dispatch change event
                const changeEvent = new Event('change', { bubbles: true });
                idInput.dispatchEvent(changeEvent);

                // Clean up the URL immediately
                window.history.replaceState({}, '', newUrl);

                // Add small delay to ensure value is set
                setTimeout(() => {
                    // Click the submit button
                    submitButton.click();
                    
                    // Wait a bit for the form to process
                    setTimeout(() => {
                        // Show success message
                        alert('<?php esc_html_e('Dashboard ID added successfully! it can take a few minutes to be active.', 'contact-forms-anti-spam'); ?>');
                    }, 500);
                }, 100);

                return true;
            } catch (error) {
                console.error('<?php esc_html_e('Error updating ID:', 'contact-forms-anti-spam'); ?>', error);
                window.history.replaceState({}, '', newUrl);
                return false;
            }
        }

        // Add to DOMContentLoaded to ensure elements exist
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const hasPrivateFileId = urlParams.has('private_file_id');
            
            // Only proceed if private_file_id exists in URL
            if (hasPrivateFileId) {
                try {
                    if (!maspikUpdatePrivateFileId()) {
                        const newUrl = window.location.pathname + "?page=maspik";
                        window.history.replaceState({}, '', newUrl);
                    }
                } catch (error) {
                    console.error('<?php esc_html_e('Error in maspikUpdatePrivateFileId:', 'contact-forms-anti-spam'); ?>', error);
                    const newUrl = window.location.pathname + "?page=maspik";
                    window.history.replaceState({}, '', newUrl);
                }
            }
        });
        <?php } // END is supporting ?>
    </script>

    <?php if (!cfes_is_supporting("general")) { ?>

        <!-- Pro Popup -->
        <div id="popup-background"></div>
        <div id="pro-popup" class="maspik-popup-wrap">
            <div class="maspik-popup">
                <div class="maspik-popup-header">
                    <h3><?php esc_html_e('Upgrade to Premium Version', 'contact-forms-anti-spam'); ?></h3>
                    <button class="close-popup">&times;</button>
                </div>
                <div class="maspik-popup-content">
                    <p><?php esc_html_e('This feature is only available for Pro users.', 'contact-forms-anti-spam'); ?></p>
                    <p><b><?php esc_html_e('Check out what you get with Maspik PRO:', 'contact-forms-anti-spam'); ?></b></p>
                    <ul>
                        <li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Custom spam API for multiple sites', 'contact-forms-anti-spam'); ?></li>
                        <li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Country-based filtering', 'contact-forms-anti-spam'); ?></li>
                        <li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Language detection & blocking', 'contact-forms-anti-spam'); ?></li>
                        <li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Premium support', 'contact-forms-anti-spam'); ?></li>
                    </ul>
                    <p><b><?php esc_html_e('Start blocking spam like a Pro!', 'contact-forms-anti-spam'); ?></b></p>
                    <div class="maspik-popup-buttons">
                        <a href="https://wpmaspik.com/?ref=getpro" target="_blank" class="maspik-btn-self"><?php esc_html_e('Upgrade Now', 'contact-forms-anti-spam'); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Listen for clicks on pro accordion content
                document.querySelectorAll('.maspik-not-pro .maspik-accordion-content').forEach(content => {
                    content.addEventListener('click', (e) => {
                        // Check if click is not on an interactive element
                        if (!e.target.closest('button, input, select, a')) {
                            e.preventDefault();
                            e.stopPropagation();
                            const proPopup = document.getElementById('pro-popup');
                            proPopup.classList.add('active');
                            document.getElementById('popup-background').style.display = 'block';
                        }
                    });
                });

                // Remove the unnecessary event listener since the button is already an <a> tag with target="_blank"
                
                // Close popup when clicking close button
                document.querySelector('#pro-popup .close-popup').addEventListener('click', () => {
                    document.getElementById('pro-popup').classList.remove('active');
                    document.getElementById('popup-background').style.display = 'none';
                });

                // Close popup when clicking outside
                document.addEventListener('click', (event) => {
                    if (!event.target.closest('#pro-popup') && 
                        !event.target.closest('.maspik-not-pro .maspik-accordion-content')) {
                        document.getElementById('pro-popup').classList.remove('active');
                        document.getElementById('popup-background').style.display = 'none';
                    }
                });

                // Close popup with ESC key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        document.getElementById('pro-popup').classList.remove('active');
                        document.getElementById('popup-background').style.display = 'none';
                    }
                });
            });
        </script>
    <?php } // END is not supporting ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Protected Form Types: delegate via generic system to Supported forms accordion
            const protectedFormTypes = document.querySelectorAll('.maspik-protected-forms .form-type');
            if (protectedFormTypes.length) {
                protectedFormTypes.forEach(item => {
                    item.classList.add('maspik-accordion-link');
                    item.setAttribute('data-accordion-target', 'form-option-accordion');
                });
            }

            // Generic handler: any element with .maspik-accordion-link and data-accordion-target
            const accordionLinks = document.querySelectorAll('.maspik-accordion-link[data-accordion-target]');
            console.log('[Maspik] Found accordion links:', accordionLinks.length);
            accordionLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = link.getAttribute('data-accordion-target');
                    console.log('[Maspik] Click on accordion link', {
                        text: link.textContent ? link.textContent.trim() : '',
                        targetId
                    });

                    if (!targetId) {
                        console.warn('[Maspik] accordion link without data-accordion-target', link);
                        return;
                    }

                    // Target can be an accordion item wrapper, or the header itself
                    let accordionItem = document.getElementById(targetId) || document.querySelector(`.${targetId}`);
                    if (!accordionItem) {
                        console.error('[Maspik] No accordion item found for target', targetId);
                        return;
                    }

                    // If the target is the header itself, use its parent as the item
                    if (accordionItem.classList.contains('maspik-accordion-header') && accordionItem.parentElement) {
                        accordionItem = accordionItem.parentElement;
                    }

                    // Smooth scroll with small offset for admin top bar
                    const rect = accordionItem.getBoundingClientRect();
                    const offset = 80;
                    const y = rect.top + window.pageYOffset - offset;
                    console.log('[Maspik] Scrolling to', { top: y });
                    window.scrollTo({ top: y, behavior: 'smooth' });

                    // Find header/content and open accordion if needed
                    let header = accordionItem.querySelector('.maspik-accordion-header');
                    let content = accordionItem.querySelector('.maspik-accordion-content');

                    // Fallback: if target itself is the header
                    if (!header && accordionItem.classList.contains('maspik-accordion-header')) {
                        header = accordionItem;
                        content = header.nextElementSibling;
                    }

                    console.log('[Maspik] Accordion parts', {
                        hasHeader: !!header,
                        hasContent: !!content,
                        headerClasses: header ? header.className : null
                    });

                    if (header) {
                        console.log('[Maspik] Toggling accordion via header.click()');
                        header.click();
                    } else {
                        console.warn('[Maspik] No accordion header found to toggle');
                    }
                });
            });
        });
    </script>

</div>
<?php

    wp_enqueue_script('custom-ajax-script', plugin_dir_url(__DIR__). 'maspik-ajax-script.js', array('jquery'), MASPIK_VERSION, true);
    wp_localize_script('custom-ajax-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    
    // Localize script for AI secret generation
    wp_localize_script('custom-ajax-script', 'maspik_ajax', array('nonce' => wp_create_nonce('maspik_ajax_nonce')));


?>
