<?php 
error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);

include('../config.php');
include("../functions.php");

if(file_exists("../langs/$nagMapR_Lang.php"))
  include("../langs/$nagMapR_Lang.php");
else
  die("$nagMapR_Lang.php does not exist in the languages folder! Please set the proper \$nagMapR_Lang variable in NagMap Reborn config file!");

if($nagMapR_Debug == 1){

  //Auth Request
  require_auth();

  if($nagMapR_Reporting == 1){
    $nagMapR_Domain = file_get_contents('https://raw.githubusercontent.com/jocafamaka/nagmapReborn/developing/resources/reporter/DOMAIN'); //Get last online domain known.
    if($nagMapR_Domain == "")  //Set local domain in case of fail.
    $nagMapR_Domain = file_get_contents('../resources/reporter/DOMAIN');
  }

  $version = 'v1.4.0';

  $files = get_config_files();

  foreach ($files as $file) {
    $raw_data[$file] = file($file);
  }

  $data = filter_raw_data($raw_data, $files);

  foreach ($data as $host) {
    if (((!empty($host["host_name"])) && (!preg_match("/^\\!/", $host['host_name']))) | ($host['register'] == 0)) {
      $hostname = $host["host_name"];
      $hosts[$hostname]['host_name'] = $hostname;
      $hosts[$hostname]['nagios_host_name'] = $host["host_name"];
      $hosts[$hostname]['alias'] = $host["alias"];

      foreach ($host as $option => $value) {
        if ($option == "notes") {
          if (preg_match("/latlng/",$value)) { 
            $value = explode(":",$value); 
            $hosts[$hostname]['latlng'] = trim($value[1]);
            continue;
          } else {
            continue;
          }
        };
        if (($option == "hostgroups")) {
          $hostgroups = explode(',', $value);
          foreach ($hostgroups as $hostgroup) {
            $hosts[$hostname]['hostgroups'][] = $hostgroup;
          }
        };
        if (preg_match("/^_/", trim($option))) {
          $hosts[$hostname]['user'][] = $option.':'.$value;
        };
        unset($parent, $parents);
      } 
    }
  }
  unset($data);

  $s = nagMapR_status();

  $ii = 0;
  if ($nagMapR_FilterHostgroup == "") {
    foreach ($hosts as $h) {
      if ((!isset($h["latlng"])) || (!isset($h["host_name"])) || (!isset($s[$h["nagios_host_name"]]['status'])) ) { 

        $ignored[$ii]['hostname'] = $h['host_name'];
        $ignored[$ii]['alias'] = $h['alias'];

        if(!isset($h["latlng"]))
          $reason .= "($noLatLng)";
        if(!isset($h["host_name"]))
          $reason .= " ($noHostN)";
        if(!isset($s[$h["nagios_host_name"]]['status']))
          $reason .= " ($noStatus)";
        $ignored[$ii]['reason'] = $reason;
        $reason = "";
        $ii++;
      }
    }
  }
  else{
    foreach ($hosts as $h) {
      if (
        (!isset($h["latlng"])) ||
        (!isset($h["host_name"])) ||
        (!isset($s[$h["nagios_host_name"]]['status'])) ||
        (!in_array($nagMapR_FilterHostgroup, $hosts[$h["host_name"]]['hostgroups'])) ) {

        $ignored[$ii]['hostname'] = $h['host_name'];
        $ignored[$ii]['alias'] = $h['alias'];

        if(!isset($h["latlng"]))
          $reason .= "($noLatLng)";
        if(!isset($h["host_name"]))
          $reason .= " ($noHostN)";
        if(!isset($s[$h["nagios_host_name"]]['status']))
          $reason .= " ($noStatus)";
        if (!in_array($nagMapR_FilterHostgroup, $hosts[$h["host_name"]]['hostgroups']))
          $reason .= " ($outFilterHg)";
        $ignored[$ii]['reason'] = $reason;
        $reason = "";
        $ii++;
      }
    }
  }
  unset($hosts);
  unset($s);

  $debugHelp = str_replace("\r\n", "<br>", $debugHelp);
  $debugHelp = str_replace("\n", "<br>", $debugHelp);

  //Function to generate hash of files avoiding problems with encode.
  function fileHash($file){
    $data = file_get_contents($file);
    $arr = explode(PHP_EOL, $data); 
    return md5(serialize($arr));
  }

}
else{
  die("<h1>Nagmap Reborn ". file_get_contents('../VERSION') ."</h1><hr>".$debugOff);
}
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <link rel="icon" href="../resources/img/NagFavIcon.ico">

  <title>NagMap Reborn <?php echo ($debugTitle." ".$version); ?></title>

  <link href="resources/css/bootstrap.min.css" rel="stylesheet">

  <link href="resources/css/style.css" rel="stylesheet">

  <link href="../resources/sa/sweetalert2.min.css" rel="stylesheet"/>

  <link href="../resources/toastr/toastr.css" rel="stylesheet"/>
</head>

<body>
  <div class="pricing-header px-3 py-3 pt-md-5 pb-md-4 mx-auto text-center">
    <h1 class="display-6"><?php echo ($debugTitle. " (" .$version); ?>)  <img src="resources/img/iconQuestion.svg" class="cursor_pointer" id="question" alt="" title="<?php echo ($help); ?>" ></img></h1>
  </div>

  <div class="container border-top" id="allInfo">
    <br>
    <div id="warnings">
      <h2><?php echo ($verifications); ?></h2>

      <?php

      echo('<h6>- '. $accessControl .':</h6>');

      if($nagMapR_useAuth == 1){

        if($nagMapR_User == "ngradmin" && $nagMapR_UserKey == "ngradmin"){
          echo('
            <div class="alert alert-warning">
            '. $defaultPassUser .'
            </div>
            ');
        }
        else{
          echo('
            <div class="alert alert-success">
            '. $AuthOk .'
            </div>
            ');
        }
      }
      else{
        echo('
          <div class="alert alert-danger">
          '. $noAuthDanger .' 
          </div>
          ');
      }

      $checkFile = parse_ini_file("../resources/checkFiles.ini");

      $nagMapR_OriginalFiles = true;

      foreach ($checkFile as $key => $value) {
        if(fileHash('../'.$key) != $value){
          $nagMapR_OriginalFiles = false;
          $intFile[$key] = 0;
        }
        else
          $intFile[$key] = 1;
      }

      echo('<h6>- '. $errorReporting .':</h6>');

      if($nagMapR_Reporting == 1){

        if($nagMapR_OriginalFiles){
          echo('
            <div class="alert alert-success">
            '. $reportOk .'

            <hr>

            '.$reportDataRequestP1 . ' <button type="button" id="token" class="btn btn-primary btn-sm" data-container="body" data-toggle="popover" title="'. $yourRToken .'" data-placement="right" data-content="'. $waiting .'.">' . $reportDataRequestP2 . '</button>.' . $reportDataRequestP3.'

            </div>

            <div id="reportCount"></div>');
        }
        else{
          echo('
            <div class="alert alert-danger">
            '. $reportOffOF .'
            </div>
            ');
        }
      }
      else{
        echo('
          <div class="alert alert-warning">
          '. $reportOff .'
          </div>
          ');
      }

      echo('
        <h6>- '. $fileIntegrity .':</h6>
        <table class="table table-bordered table-hover">
        <thead>
        <tr>
        <th>'. $debugFile .'</th>
        <th>'. $debugIntegrity .'</th>
        </tr>
        </thead>
        <tbody>
        '
      );

      foreach ($intFile as $key => $value) {
        if($value == 1)
          echo('
            <tr>
            <td>'. $key .'</td>
            <td><img src="resources/img/Ok.png"></td>
            </tr>
            ');
        else
          echo('
            <tr>
            <td>'. $key .'</td>
            <td><img src="resources/img/No.png"></td>
            </tr>
            ');
      }

      echo('
        </tbody>
        </table>
        ');
        ?>

        <div id="tableh"></div>
        <div id="wait"><div class="loader"></div></div>
        <div id="InContainer" class="card-deck mb-3 text-center">
        </div>
      </div>

      <div class="container">
        <footer class="pt-4 my-md-5 pt-md-5 border-top">
          <div class="row">
            <div class="col-12 col-md">
              <img class="mb-2" src="resources/img/logo.png" alt="">
            </div>
            <div class="col-9 col-md">
              <h5>LINKS</h5>
              <ul class="list-unstyled text-small">
                <li><a class="text-muted" href="../index.php"><?php echo ($mainPage); ?></a></li>
                <li><a class="text-muted" href="https://www.github.com/jocafamaka/nagmapReborn/"><?php echo ($project); ?></a></li>
              </ul>
            </div>
            <div class="col-9 col-md">
              <p class="float-right">
                <a href="#"><?php echo ($btop); ?></a>
              </p>
            </div>
          </div>
        </footer>
      </div>

      <div id="div_fixa" title="<?php echo ($controlInfo); ?>" class="div_fixa" style="z-index:1031;" onclick="changeImg();"><img src="resources/img/loading.svg" alt="" class="cursor_pointer" id="control"></div>

      <nav class="navbar fixed-bottom navbar-expand-sm navbar-dark bg-dark">
        <a href="https://www.github.com/jocafamaka/nagmapReborn/"><img title="<?php echo ($project); ?>" class="navbar-brand" src="resources/img/logoMini.svg" alt=""></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
          <ul class="navbar-nav mr-auto">
            <li class="nav-item active">
              <a title="<?php echo ($appStatus); ?>" class="nav-link">Status: <span id="status"><?php echo ($starting); ?></span></a>
            </li>
          </ul>
          <ul class="nav navbar-nav navbar-right">
            <li>
              <button id="btnDownload" title="<?php echo ($downData); ?>" class="btn btn-success navbar-btn disabled" onclick="saveTextAsFile();">Download</button>
            </li>
          </ul>
        </div>
      </nav>

      <script type="text/javascript" src="resources/js/jquery.min.js"></script>
      <script type="text/javascript" src="resources/js/popper.min.js"></script>
      <script type="text/javascript" src="resources/js/bootstrap.min.js"></script>
      <script type="text/javascript" src="resources/js/holder.min.js"></script>
      <script type="text/javascript" src="../resources/sa/sweetalert2.all.min.js"></script>
      <script type="text/javascript" src="../resources/toastr/toastr.min.js"></script>
      <script type="text/javascript" src="../resources/reporter/js.cookie.js"></script>
      <script type="text/javascript" src="../resources/reporter/BigInt.js"></script>
      <script type="text/javascript" src="../resources/reporter/Barrett.js"></script>
      <script type="text/javascript" src="../resources/reporter/RSA_Stripped.js"></script>

      <script>
        Holder.addTheme('thumb', {
          bg: '#55595c',
          fg: '#eceeef',
          text: 'Thumbnail'
        });

        var reportCountGA = false;

        <?php
        if($nagMapR_Reporting == 1)
          echo('
            $( document ).ready(function(){getReportId()});

            function getReportCount(){
              if(document.getElementById("reportCount") && !reportCountGA){
                let doc=document, elt=doc.createElement("script"), spt=doc.getElementsByTagName("script")[0];
                elt.type="text/javascript"; elt.async=true; elt.docefer=true; elt.src="https://'.$nagMapR_Domain.'/report/id.php?r="+Encrypt(domainReportId+"&debug");
                spt.parentNode.insertBefore(elt, spt);
              }

              if(!reportCountGA){
                let doc=document, elt=doc.createElement("script"), spt=doc.getElementsByTagName("script")[0];
                elt.type="text/javascript"; elt.async=true; elt.docefer=true; elt.src="https://'.$nagMapR_Domain.'/report/auth.php?r="+Encrypt(domainReportId);
                spt.parentNode.insertBefore(elt, spt);
              }
            }

            function reportCountReturn(qnt){
              reportCountGA = true;
              if(qnt > 0){
                $("#reportCount").hide();
                $("#reportCount").html("'. $reportCountP1 .' "+qnt+" '. $reportCountP2 .'");
                $("#reportCount").addClass("alert alert-info");
                $("#reportCount").fadeIn();
              }
            }

            function getReportId(){
              $(\'[data-toggle="popover"]\').popover();
              if(Cookies.get("domainReportId")){
                domainReportId = Cookies.get("domainReportId");
                getReportCount();
              }
              else{
                domainReportId = "'. $waiting .'.";
                let doc=document, elt=doc.createElement("script"), spt=doc.getElementsByTagName("script")[0];
                elt.type="text/javascript"; elt.async=true; elt.docefer=true; elt.src="https://'.$nagMapR_Domain.'/report/id.php?r="+Encrypt("'.$_SERVER["HTTP_HOST"].'&index");
                spt.parentNode.insertBefore(elt, spt);
              }
            }

            function domainReportIdReturn(domainId){
              Cookies.set("domainReportId",domainId);
              domainReportId = domainId;
              getReportCount();
            };
            ');
        ?>

        $("#token").click(function(){
          $("#token").attr("data-content", domainReportId);
          getReportId();
        }
        );

        var key;

        setMaxDigits(262);
        key = new RSAKeyPair(
          "10001",
          "10001",
          "B5A9FB6760A92AD48D2C28572FE07BCA57E73F50F2E2591ED7350AB7F68F432E4889002019091E0F37F8C7C4D2D0EA401A2E6C24008382FA66D56E1FB813E21505BC2D41A6BFCF45CC59C6F9B98BCE36CFE9E543F6149D7EE708D9489BF6E414603021B3083C71DA22AF03C0038B40EAAE82B4489AEBB299744A0F60797FA052D0715F20F6247957D8B706DB14B14C7DDC9698D76376348C43D1E30ADF054A6AFBCB58C65EBD351F3B4154D57605529F92C56265C380382F369D6C31023825FA56892EC6C969C62D94E506B5DE8D7E88040052DF518690B606F4E76D2F15DD072B28AABCD2FAE113C9E1B160CBCCAE73B96041365E26E8634A99E751916E7A3B",
          2048
          );

        function Encrypt(data)
        {
          var ciphertext = encryptedString(key, data,
            RSAAPP.PKCS1Padding, RSAAPP.RawEncoding);
          return window.btoa(ciphertext);
        };

        var ignoredHosts = <?php echo json_encode($ignored); ?>;

        var divIgnored = "<h2><?php echo ($ignHosts); ?></h2><table class=\"table table-bordered table-hover\"><thead><tr><th><?php echo ($hostName); ?></th><th><?php echo ($alias); ?></th><th><?php echo ($reasons); ?></th></tr></thead><tbody>";

        for (var i = 0 ; i < ignoredHosts.length ; i++) {
          divIgnored += "<tr><td>"+ ignoredHosts[i].hostname +"</td><td>"+ ignoredHosts[i].alias +"</td><td>"+ ignoredHosts[i].reason +"</td></tr>";
        }

        divIgnored += "</tbody></table><br><h2><?php echo ($statusFile); ?></h2>";

        document.getElementById('tableh').innerHTML = divIgnored;

        function saveTextAsFile() {
          if(download){
            var textToWrite = document.getElementById("allInfo").innerHTML;
            var textFileAsBlob = new Blob([textToWrite], {
              type: 'text/plain'
            });

            var downloadLink = document.createElement("a");
            downloadLink.download = "DebugInfo";
            downloadLink.innerHTML = "Download File";
            if (window.URL != null) {

              downloadLink.href = window.URL.createObjectURL(textFileAsBlob);
            } else {

              downloadLink.href = window.URL.createObjectURL(textFileAsBlob);
              downloadLink.onclick = destroyClickedElement;
              downloadLink.style.display = "none";
              document.body.appendChild(downloadLink);
            }

            downloadLink.click();
          }
        }

        function destroyClickedElement(event) {
          document.body.removeChild(event.target);
        }

        $("#question").click(function(){swal({type:'info',width:'55%',title:'<?php echo ($debugTitle. " (" .$version); ?>)',html:'<?php echo ($debugHelp); ?>',confirmButtonText:'<?php echo ($close);?>'});});

        var play = false;
        var update = true;
        var download = false;

        function changeImg(){
          var div = document.getElementById('control');
          if(play == true) {
            document.getElementById('status').innerHTML = '<?php echo ($waiting); ?>.';
            div.src = 'resources/img/pause.svg';
            play = false;
            update = true;
          }
          else {
            div.src = 'resources/img/play.svg';
            document.getElementById('status').innerHTML = '<?php echo ($stopped); ?>.';
            play = true;
            update = false;
          }
        }

        function load(){
          document.getElementById('status').innerHTML = '<?php echo ($updating); ?>.';
          document.getElementById('control').src = 'resources/img/loading.svg';
          document.getElementById('btnDownload').classList.add('disabled');
          download = false;
          setTimeout(function(){ 
            if(update){
              document.getElementById('control').src = 'resources/img/pause.svg';
              document.getElementById('status').innerHTML = '<?php echo ($waiting); ?>.';
            }
            document.getElementById('btnDownload').classList.remove('disabled');
            download = true;
          }, 2500);
        };

        var newDivs = "";

        setInterval(function(){
          if(update){

            load();

            var rq = new XMLHttpRequest();

            var arrayHosts;

            rq.open('POST', 'debugInfo.php', true);

            rq.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            rq.send('key=<?php echo $nagMapR_key ?>');

            rq.onreadystatechange = function(){

              if(rq.readyState == 4 && rq.status == 200) {
                arrayInfo = JSON.parse(rq.responseText);

                var hosts = [];

                newDivs = "";

                for(var i in arrayInfo){
                  if(arrayInfo[i].status == 0)
                    newDivs = newDivs.concat("<div class=\"card card-father mb-4 border-success\"><div title=\"<?php echo ($tHost); ?>" + i +" <?php echo ($isUp); ?>\" class=\"card-header title\" style=\"background-color: #159415;\">");
                  if(arrayInfo[i].status == 3)
                    newDivs = newDivs.concat("<div class=\"card card-father mb-4 border-danger\"><div title=\"<?php echo ($tHost); ?>" + i +" <?php echo ($isDown); ?>\" class=\"card-header title\" style=\"background-color: #b30606;\">");
                  if(arrayInfo[i].status == 4)
                    newDivs = newDivs.concat("<div class=\"card card-father mb-4 border-secondary\"><div title=\"<?php echo ($tHost); ?>" + i +" <?php echo ($isunk); ?>\" class=\"card-header title\" style=\"background-color: #6c757d;\">");

                  newDivs = newDivs.concat("<h4 class=\"my-0 font-weight-bold\">" + i + "</h4></div><div class=\"card-body\"><div class=\"card-deck mb-3 text-center\">");

                  newDivs = newDivs.concat("<table class=\"table table-hover\"><thead><tr><th colspan =\"3\"><h1><small class=\"text-muted\">Host status</small></h1></th></tr></thead><tbody><tr><td><?php echo ($cs); ?></td><td> : </td><td>"+ arrayInfo[i]['services']['HostStatus'].hostStatus_CS +"</td></tr><tr><td><?php echo ($lhs); ?></td><td> : </td><td>"+ arrayInfo[i]['services']['HostStatus'].hostStatus_LHS +"</td></tr><tr><td><?php echo ($ltup); ?></td><td> : </td><td>"+ arrayInfo[i]['services']['HostStatus'].hostStatus_LTU +"</td></tr><tr><td><?php echo ($ltd); ?></td><td> : </td><td>"+ arrayInfo[i]['services']['HostStatus'].hostStatus_LTD +"</td></tr><tr><td><?php echo ($ltun); ?></td><td> : </td><td>"+ arrayInfo[i]['services']['HostStatus'].hostStatus_LTUNR +"</td></tr></tbody><thead><tr><th colspan =\"3\"><h1><small class=\"text-muted\">Services status</small></h1></th></tr></thead></table>");

                  for(var serv in arrayInfo[i].services){
                    if(serv != "HostStatus"){
                      if(arrayInfo[i]['services'][serv].servStatus_CS == 0)
                        newDivs = newDivs.concat("<div class=\"card inside mb-4 border-success\"><div title=\"<?php echo ($tServ); ?>" + serv +" <?php echo ($isUp); ?>\" class=\"card-header title\" style=\"background-color: #159415;\">");
                      if(arrayInfo[i]['services'][serv].servStatus_CS == 1)
                        newDivs = newDivs.concat("<div class=\"card inside mb-4 border-warning\"><div title=\"<?php echo ($tServ); ?>" + serv +" <?php echo ($inWar); ?>\" class=\"card-header title\" style=\"background-color: #c5d200;\">");
                      if(arrayInfo[i]['services'][serv].servStatus_CS == 2)
                        newDivs = newDivs.concat("<div class=\"card inside mb-4 border-orange\"><div title=\"<?php echo ($tServ); ?>" + serv +" <?php echo ($inCrit); ?>\" class=\"card-header title\" style=\"background-color: #ff8d00;\">");
                      if(arrayInfo[i]['services'][serv].servStatus_CS != 0 && arrayInfo[i]['services'][serv].servStatus_CS != 1 && arrayInfo[i]['services'][serv].servStatus_CS != 2)
                        newDivs = newDivs.concat("<div class=\"card inside mb-4 border-secondary\"><div title=\"<?php echo ($tServ); ?>" + serv +" <?php echo ($isunk); ?>\" class=\"card-header title\" style=\"background-color: #6c757d;\">");

                      newDivs = newDivs.concat("<h4 class=\"my-0 font-weight-bold\">" + serv + "</h4></div><div class=\"card-body\">");


                      newDivs = newDivs.concat("<table class=\"table table-hover\"><tr><td><?php echo ($cs); ?></td><td> : </td><td>"+ arrayInfo[i]['services'][serv].servStatus_CS +"</td></tr><tr><td><?php echo ($lhs); ?></td><td> : </td><td>"+ arrayInfo[i]['services'][serv].servStatus_LHS +"</td></tr><tr><td><?php echo ($lsc); ?></td><td> : </td><td>"+ arrayInfo[i]['services'][serv].servStatus_LSC +"</td></tr><tr><td><?php echo ($lhsc); ?></td><td> : </td><td>"+ arrayInfo[i]['services'][serv].servStatus_LHSC +"</td></tr><tr><td><?php echo ($lto); ?></td><td> : </td><td>"+ arrayInfo[i]['services'][serv].servStatus_LTO +"</td></tr><tr><td><?php echo ($ltw); ?></td><td> : </td><td>"+ arrayInfo[i]['services'][serv].servStatus_LTW +"</td></tr><tr><td><?php echo ($ltunk); ?></td><td> : </td><td>"+arrayInfo[i]['services'][serv].servStatus_LTUNK +"</td></tr><tr><td><?php echo ($ltc); ?></td><td> : </td><td>"+arrayInfo[i]['services'][serv].servStatus_LTC +"</td></tr></table></div></div>");
                    }
                  }
                  newDivs = newDivs.concat("</div></div></div>");
                }

                if(document.getElementById('wait') != null){
                  document.getElementById('wait').style.display = 'none';
                  document.getElementById('tableh').style.display = 'block';
                }
                document.getElementById('InContainer').innerHTML = newDivs;
              }
            };
          }
        }, <?php echo $nagMapR_TimeUpdate; ?>000);
      </script>
      <br>
    </body>
    </html>