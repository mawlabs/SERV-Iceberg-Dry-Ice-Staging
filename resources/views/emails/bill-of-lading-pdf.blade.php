@php
    // Sample values come from Docs/Nippon_ACI_July10.pdf; pass real data when integrating.
    $invoiceNumber  = $invoiceNumber  ?? '10598';
    $date           = $date           ?? 'Jul 10 2026';
    $customerNumber = $customerNumber ?? '3379';
    $consignee      = $consignee      ?? ['name' => 'ACI AIRCARGO INC', 'address' => '5200 Miller Road, Suite 104 Richmond, B.C.', 'phone' => '(778) 377-7233'];
    $weightKg       = $weightKg       ?? '15.91';
    $signerName     = $signerName     ?? 'Tyler Anderson';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill of Lading</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; color: #000; }
        .t { position: absolute; white-space: nowrap; }
        .r { position: absolute; background: #000; }
    </style>
</head>
<body>
<img src="{{ \App\Support\InvoiceAssets::logoDataUri() }}" style="position:absolute; left:7.3pt; top:2.3pt; width:234.9pt; height:131.5pt;" alt="logo" />
<div style="position:absolute; left:304.6pt; top:410.4pt; width:213pt; height:19.4pt; background:#ffff42;"></div>

<div class="r" style="left:0pt;top:153pt;width:560.3pt;height:1pt;"></div>
<div class="r" style="left:396pt;top:150.5pt;width:85pt;height:1.0pt;"></div>
<div class="r" style="left:20pt;top:166.9pt;width:82.8pt;height:1.1pt;"></div>
<div class="r" style="left:218.5pt;top:169.3pt;width:83.4pt;height:1.1pt;"></div>
<div class="r" style="left:18pt;top:216.1pt;width:499pt;height:0.9pt;"></div>
<div class="r" style="left:515.9pt;top:216.1pt;width:1.1pt;height:13.6pt;"></div>
<div class="r" style="left:18pt;top:228.6pt;width:499pt;height:1.1pt;"></div>
<div class="r" style="left:18pt;top:216.1pt;width:0.7pt;height:13.6pt;"></div>
<div class="r" style="left:65.6pt;top:216.1pt;width:0.7pt;height:13.6pt;"></div>
<div class="r" style="left:294pt;top:216.1pt;width:0.7pt;height:13.6pt;"></div>
<div class="r" style="left:343.3pt;top:216.1pt;width:0.7pt;height:13.6pt;"></div>
<div class="r" style="left:396pt;top:216.1pt;width:0.7pt;height:13.6pt;"></div>
<div class="r" style="left:453.8pt;top:216.1pt;width:0.7pt;height:13.6pt;"></div>
<div class="r" style="left:18pt;top:234.8pt;width:499pt;height:0.8pt;"></div>
<div class="r" style="left:515.9pt;top:234.8pt;width:1.1pt;height:16.4pt;"></div>
<div class="r" style="left:18pt;top:250.3pt;width:499pt;height:0.9pt;"></div>
<div class="r" style="left:18pt;top:234.8pt;width:0.7pt;height:16.4pt;"></div>
<div class="r" style="left:65.6pt;top:234.8pt;width:0.7pt;height:16.4pt;"></div>
<div class="r" style="left:343.3pt;top:234.8pt;width:0.7pt;height:16.4pt;"></div>
<div class="r" style="left:396pt;top:234.8pt;width:1.5pt;height:16.4pt;"></div>
<div class="r" style="left:453.8pt;top:234.8pt;width:0.7pt;height:16.4pt;"></div>
<div class="r" style="left:18pt;top:266.9pt;width:443.3pt;height:0.9pt;"></div>
<div class="r" style="left:18.7pt;top:431pt;width:212.8pt;height:1.7pt;"></div>
<div class="r" style="left:301pt;top:431pt;width:212.6pt;height:1.7pt;"></div>
<div class="r" style="left:305.2pt;top:461.5pt;width:209.6pt;height:1.7pt;"></div>
<div class="r" style="left:19.9pt;top:461.5pt;width:212.8pt;height:1.7pt;"></div>
<div class="r" style="left:112.4pt;top:493.8pt;width:317.3pt;height:1.2pt;background:#800000;"></div>
<div class="r" style="left:148.9pt;top:508pt;width:244.3pt;height:1.2pt;background:#800000;"></div>
<div class="r" style="left:20.2pt;top:524.6pt;width:528.6pt;height:0.9pt;background:#333399;"></div>
<div class="r" style="left:236.4pt;top:537.4pt;width:96.1pt;height:0.8pt;background:#333399;"></div>
<div class="t" style="left:288pt;top:19pt;font-size:16pt;line-height:17.9pt;color:#000080;">STRAIGHT BILL OF LADING</div>
<div class="t" style="left:322pt;top:38pt;font-size:16pt;line-height:17.9pt;color:#000080;">NOT NEGOTIABLE</div>
<div class="t" style="left:390pt;top:73pt;font-size:10pt;line-height:11.2pt;color:#000;">Invoice Number:</div>
<div class="t" style="left:469pt;top:70pt;font-size:14pt;line-height:15.7pt;color:#000;">{{ $invoiceNumber }}</div>
<div class="t" style="left:390pt;top:109pt;font-size:10pt;line-height:11.2pt;color:#000;">Date:</div>
<div class="t" style="left:419pt;top:109pt;font-size:10pt;line-height:11.2pt;color:#000;">{{ $date }}</div>
<div class="t" style="left:378pt;top:124pt;font-size:10pt;line-height:11.2pt;color:#000;">Customer Number:</div>
<div class="t" style="left:469pt;top:124pt;font-size:10pt;line-height:11.2pt;color:#000;">{{ $customerNumber }}</div>
<div class="t" style="left:281pt;top:139pt;font-size:10pt;line-height:11.2pt;color:#000;">Carrier Waybill Number:</div>
<div class="t" style="left:19pt;top:104pt;font-size:10pt;line-height:11.2pt;color:#000;">Iceberg Dry Ice</div>
<div class="t" style="left:19pt;top:115pt;font-size:10pt;line-height:11.2pt;color:#000;">329 Churchill Avenue</div>
<div class="t" style="left:19pt;top:127pt;font-size:10pt;line-height:11.2pt;color:#000;">New Westminster BC &nbsp;V3L 4P5</div>
<div class="t" style="left:19pt;top:138pt;font-size:10pt;line-height:11.2pt;color:#000;">(604) 524-0609</div>
<div class="t" style="left:20pt;top:157pt;font-size:10pt;line-height:11.2pt;color:#000;font-weight:bold;">SHIPPER (FROM)</div>
<div class="t" style="left:20pt;top:169pt;font-size:12pt;line-height:13.4pt;color:#000;">Iceberg Dry Ice</div>
<div class="t" style="left:219pt;top:159pt;font-size:10pt;line-height:11.2pt;color:#000;font-weight:bold;">CONSIGNEE (TO)</div>
<div class="t" style="left:219pt;top:174pt;font-size:10pt;line-height:11.2pt;color:#000;">{{ $consignee['name'] }}</div>
<div class="t" style="left:219pt;top:188pt;font-size:10pt;line-height:11.2pt;color:#000;">{{ $consignee['address'] }}</div>
<div class="t" style="left:219pt;top:202pt;font-size:10pt;line-height:11.2pt;color:#000;">{{ $consignee['phone'] }}</div>
<div class="t" style="left:34pt;top:218pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">UN</div>
<div class="t" style="left:72pt;top:218pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">Pieces</div>
<div class="t" style="left:112pt;top:218pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">Description of Goods</div>
<div class="t" style="left:357pt;top:218pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">CLASS</div>
<div class="t" style="left:400pt;top:218pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">PKG GRP</div>
<div class="t" style="left:458pt;top:218pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">WEIGHT/Kgm</div>
<div class="t" style="left:20pt;top:236pt;font-size:10pt;line-height:11.2pt;color:#000;">UN1845</div>
<div class="t" style="left:115pt;top:236pt;font-size:10pt;line-height:11.2pt;color:#000;">Dry Ice</div>
<div class="t" style="left:368pt;top:236pt;font-size:10pt;line-height:11.2pt;color:#000;">9</div>
<div class="t" style="left:472pt;top:236pt;font-size:10pt;line-height:11.2pt;color:#000;">{{ $weightKg }}</div>
<div class="t" style="left:19pt;top:253pt;font-size:12pt;line-height:13.4pt;color:#000;font-weight:bold;">Caution</div>
<div class="t" style="left:19pt;top:270.0pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">DRY ICE IS A SKIN AND EYE IRRITANT, USE GLOVES TO HANDLE. DRY ICE IS HARMFUL IF EATEN OR SWALLOWED.  AVOID </div>
<div class="t" style="left:19pt;top:279.5pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">CONTACT WITH SKIN, MOUTH, EYES,AND CLOTHING.  MAY CAUSE SEVERE FROSTBITE OR BURNS.  IF EATEN OR IF </div>
<div class="t" style="left:19pt;top:289.0pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">FROSTBITE OCCURS, SEEK MEDICAL HELP IMEDIATELY.  DRY ICE MAKES CARBON DIOXIDE GAS AS IT SUBLIMATES </div>
<div class="t" style="left:19pt;top:298.5pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">(MELTS). DO NOT PLACE DRY ICE IN AIR-TIGHT CONTAINERS AS THEY MAY EXPLODE.  ONLY USE DRY ICE IN OPEN OR WELL</div>
<div class="t" style="left:19pt;top:308.0pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">VENTILATED AREAS, OTHERWISE A LACK OF OXYGEN (SUFFOCATION) CAN OCCUR CAUSING SERIOUS INJURY OR DEATH.  </div>
<div class="t" style="left:200pt;top:330pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">KEEP OUT OF CHILDREN&#x27;S REACH</div>
<div class="t" style="left:138pt;top:340pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">INTENTIONAL MISUSE OF DRY ICE MAY BE HARMFUL OR FATAL.</div>
<div class="t" style="left:188pt;top:349pt;font-size:8pt;line-height:9.0pt;color:#000;font-weight:bold;">USE ONLY UNDER ADULT SUPERVISION.</div>
<div class="t" style="left:33pt;top:368pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">I hereby declare that the contents of this consignment are fully and accurately described above by the proper </div>
<div class="t" style="left:32pt;top:379pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">shipping name, are properly classified and packaged, have dangerous goods safety marks properly affixed or </div>
<div class="t" style="left:41pt;top:390pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">displayed on them, and in all respects in proper condition for transportaccording to the Transportation of </div>
<div class="t" style="left:198pt;top:400pt;font-size:9pt;line-height:10.1pt;color:#000;font-weight:bold;">Dangerous Goods Regulations.  </div>
<div class="t" style="left:47pt;top:413pt;font-size:12pt;line-height:13.4pt;color:#000;">{{ $signerName }}</div>
<div class="t" style="left:20pt;top:418pt;font-size:10pt;line-height:11.2pt;color:#000;font-weight:bold;">Per </div>
<div class="t" style="left:45pt;top:431pt;font-size:10pt;line-height:11.2pt;color:#000;">for Iceberg Dry Ice Inc</div>
<div class="t" style="left:303pt;top:418pt;font-size:10pt;line-height:11.2pt;color:#000;font-weight:bold;">Per </div>
<div class="t" style="left:308pt;top:432pt;font-size:10pt;line-height:11.2pt;color:#000;">Accepted for ACI AIRCARGO INC</div>
<div class="t" style="left:22pt;top:465pt;font-size:10pt;line-height:11.2pt;color:#000;">Please print your name here.</div>
<div class="t" style="left:306pt;top:465pt;font-size:10pt;line-height:11.2pt;color:#000;">Please print your name here.</div>
<div class="t" style="left:112pt;top:482pt;font-size:12pt;line-height:13.4pt;color:#800000;font-weight:bold;">IN CASE OF DANGEROUS GOODS EMERGENCY CALL:</div>
<div class="t" style="left:149pt;top:496pt;font-size:12pt;line-height:13.4pt;color:#800000;font-weight:bold;">24 Hour Number 1-613-996-6666 CANUTEC</div>
<div class="t" style="left:20pt;top:514pt;font-size:11pt;line-height:12.3pt;color:#333399;">All totes must be returned within two weeks of delivery unless arrangements have been made with Iceberg.  </div>
<div class="t" style="left:236pt;top:526pt;font-size:11pt;line-height:12.3pt;color:#333399;">Late fees will apply.</div>
<div class="t" style="left:19pt;top:758pt;font-size:12pt;line-height:13.4pt;color:#000;">www.icebergdryice.com</div>
</body>
</html>
