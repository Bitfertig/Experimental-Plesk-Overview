<?php

// Plesk RESTful API
// https://...:8443/api/v2/


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$_ENV['REMOTE_HOST'] = '...';
$_ENV['REMOTE_BASEURL'] = 'https://'.$_ENV['REMOTE_HOST'].':8443/api/v2';
$_ENV['REMOTE_USER'] = '...';
$_ENV['REMOTE_PASS'] = '...';



function curlPleskApi($method, $path, $data = []) {usleep(1000000);

   $url = $_ENV['REMOTE_BASEURL'].$path;
   $method = strtolower($method);

   $curl = curl_init($url);
   curl_setopt($curl, CURLOPT_URL, $url);
   if ( in_array($method, ['get']) ) {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
   }
   if ( in_array($method, ['post']) ) {
      curl_setopt($curl, CURLOPT_POST, true);
   }
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

   // Alternative: https://docs.plesk.com/en-US/obsidian/api-rpc/about-rest-api.79359/#api-keys
   curl_setopt($curl, CURLOPT_USERPWD, $_ENV['REMOTE_USER'] . ':' . $_ENV['REMOTE_PASS']);

   $headers = [];
   $headers[] = "accept: application/json";
   $headers[] = "Content-Type: application/json";
   curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

   if ( in_array($method, ['post']) ) {
      //$data = '{ "name": "example.com", "description": "My website", "hosting_type": "virtual", "hosting_settings": { "ftp_login": "test_login", "ftp_password": "changeme1Q**" }, "base_domain": { "id": 7, "name": "example.com", "guid": "b623e93d-dc72-4102-b5f0-ded427cf0fb1" }, "parent_domain": { "id": 7, "name": "example.com", "guid": "b623e93d-dc72-4102-b5f0-ded427cf0fb1" }, "owner_client": { "id": 7, "login": "owner", "guid": "b623e93d-dc72-4102-b5f0-ded427cf0fb1", "external_id": "b623e93d-dc72-4102-b5f0-ded427cf0fb1" }, "ip_addresses": [ "93.184.216.34", "2606:2800:220:1:248:1893:25c8:1946" ], "ipv4": [ "93.184.216.34" ], "ipv6": [ "2606:2800:220:1:248:1893:25c8:1946" ], "plan": { "name": "Unlimited" }}';
      $data = json_encode($data);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
   }

   //for debug only!
   curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
   curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

   $response = curl_exec($curl);
   curl_close($curl);

   return $response;

}




// Create a new Domain
if ( isset($_POST['action']) && $_POST['action'] == 'create_domain' ) {

   $domain = $_POST['domain'];
   $parent_id = $_POST['parent_id'];
   $ftp_user = '...';
   $ftp_pass = 'Az123!@#$%^&*?_~';

   if ( $parent_id > 0 ) {
      $parent = json_decode(curlPleskApi('GET', '/domains/'.$parent_id));
   }

   $data = [];
   $data['name'] = $domain;
   $data['description'] = 'My dev website';
   $data['hosting_type'] = 'virtual';
   $data['hosting_settings'] = [
      "ftp_login" => $ftp_user, // only on subscription?
      "ftp_password" => $ftp_pass, // only on subscription?
      'www_root' => '/'.$domain, //.'/public',
   ];
   if ( $parent_id > 0 ) {
      $data['base_domain'] = [
         "id" => $parent->id,
         "name" => $parent->name,
         "guid" => $parent->guid
      ];
      $data['parent_domain'] = [
         "id" => $parent->id,
         "name" => $parent->name,
         "guid" => $parent->guid
      ];
   }
   /*
   $data['owner_client'] = [
      "id" => $parent->id,
      "login" => "owner",
      "guid" => $parent->guid,
      "external_id" => $parent->guid
   ]
   $data['ip_addresses'] = [
      "93.184.216.34",
      "2606:2800:220:1:248:1893:25c8:1946"
   ],
   $data['ipv4'] = [
      "93.184.216.34"
   ],
   $data['ipv6'] = [
      "2606:2800:220:1:248:1893:25c8:1946"
   ],
   $data['plan'] = [
      "name" => "Unlimited"
   ]
   */

   $created_domain = curlPleskApi('POST', '/domains', $data);
   var_export($created_domain);
   #usleep(1000000);
   exit;


   // Get all DB-Servers
   $dbservers = json_decode(curlPleskApi('GET', '/dbservers', []));
   #var_export($dbservers);
   $mysqlserver = null;
   foreach ($dbservers as $dbserver) {
      if ( $dbserver->type == 'mysql' ) {
         $mysqlserver = $dbserver;
         break;
      }
   }
   #var_export($mysqlserver);

   $db_data = [
      "name" => "familyname_project",
      "type" => "mysql",
      "parent_domain" => [
        "id" => $parent->id,
        "name" => $parent->name,
        "guid" => $parent->guid
      ],
      "server_id" => $mysqlserver->id
   ];
   $res = json_decode(curlPleskApi('POST', '/databases', $db_data));
   #var_export($res);


   echo 'Saved domain.';
}






// Output

$response_domains = json_decode(curlPleskApi('GET', '/domains'));

$domains = [];
foreach($response_domains as $response_domain) {
   /*
   {
        "id": 2,
        "created": "2020-10-29",
        "name": "domain.com",
        "ascii_name": "domain.com",
        "base_domain_id": 0,
        "guid": "acf5d8ab-5cf4-47a1-b33b-26c089d7c44a",
        "hosting_type": "standard_forwarding",
        "www_root": ""
   }
   */
   if ( $response_domain->base_domain_id == 0 ) {
      $domains[$response_domain->id] = $response_domain;
      $domains[$response_domain->id]->subdomains = [];
   }
   elseif ( $response_domain->base_domain_id > 0 ) {
      $domains[$response_domain->base_domain_id]->subdomains[] = $response_domain;
   }
}
#echo '<pre>';var_dump($domains);echo '</pre>';exit;

echo '<table style="width:100%;">';
   echo '<tr>';
      echo '<th style="text-align:left;">Name</th>';
      echo '<th style="text-align:left;">Subdomain</th>';
      echo '<th style="text-align:left;"></th>';
   echo '</tr>';
   foreach($domains as $domain) {
      if ( $domain->hosting_type == 'virtual' ) {
         echo '<tr>';
            echo '<td style="vertical-align:top;" title="ID: '. $domain->id .'&#10;GUID: '. $domain->guid .'">';
               echo $domain->name;
               #echo $domain->www_root;
            echo '</td>';
            echo '<td>';
               echo '<ul>';
               foreach($domain->subdomains as $subdomain) {
                  echo '<li>';
                     #var_export($subdomain);
                     echo '<a href="https://'.$subdomain->name.'">'.$subdomain->name.'</a>';
                  echo '</li>';
               }
               echo '<li>';
                  echo '<form method="POST" action="">';
                     echo '<input type="hidden" name="action" value="create_domain">';
                     echo '<input type="hidden" name="parent_id" value="'.$domain->id.'">';
                     echo '<input type="text" name="domain" value=".'.$domain->name.'" pattern=".+\.'.$domain->name.'">';
                     echo '<input type="submit" value="Anlegen">';
                  echo '</form>';
               echo '</li>';
               echo '</ul>';
            echo '</td>';
            echo '<td>';
               echo '<a href="https://'.$_ENV['REMOTE_HOST'].':8443/admin/subscription/overview/id/'.$domain->id.'">';
                  echo '<img src="https://'.$_ENV['REMOTE_HOST'].':8443/images/favicon.svg" width="25" color="#54bce6">';
               echo '</a>';
            echo '</td>';
         echo '</tr>';
      }
   }
echo '</table>';











// ------------------------------------------------------


// curl -X GET "https://...:8443/api/v2/ftpusers?name=exampleuser&domain=example.com" -H "accept: application/json"
/*
[
  {
    "id": 2,
    "name": "exampleuser",
    "home": "/httpdocs",
    "quota": -1,
    "permissions": {
      "write": "true",
      "read": "true"
    },
    "parent_domain": 4
  }
]
*/


// curl -X GET "https://...:8443/api/v2/databases?domain=example.com" -H "accept: application/json"
/*
[
  {
    "id": 2,
    "name": "exampledb",
    "type": "mssql",
    "parent_domain": 3,
    "server_id": 1,
    "default_user_id": 6
  }
]
*/
