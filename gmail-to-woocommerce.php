<?php
   /*
   Plugin Name: Gmail to woocommerce plugin
   
   description: A plugin to upload documents from gmail order email to woocommerce
   Author: Dhanashree Sawant 
   */

  require_once dirname(__FILE__) .'/google_api/vendor/autoload.php';

  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  
  //Function to decode message
  function decodeBody($body) {
      $rawData = $body;
      $sanitizedData = strtr($rawData,'-_', '+/');
      $decodedMessage = base64_decode($sanitizedData);
      if(!$decodedMessage){
          $decodedMessage = FALSE;
      }
      return $decodedMessage;
  }

  //Function to enqueue CSS script for admin page
  function gw_enqueue_scripts(){
    wp_enqueue_style( 'gw-style', plugins_url('/assets/css/main.css',__FILE__), array(),rand(1,1000));
  }
  add_action('admin_enqueue_scripts','gw_enqueue_scripts');
   
  //API function to get each message
  function getMessage($service, $userId, $messageId) {
      try {
          $message = $service->users_messages->get($userId, $messageId);
          // print 'Message with ID: ' . $message->getId() . ' retrieved.';
          return $message;
      } catch (Exception $e) {
          $file_path=dirname(__FILE__) . '/error_log.txt';
          $current_date='[' .date('Y-m-d H:i:s') .'] ';
          $error=$current_date .'An error occurred while accessing API: ' . $e->getMessage() . '\\n';
          $file= fopen($file_path,'w');
          fwrite($file,$error);
          fclose($file);
          $admin_email = get_option( 'gmail_error_email' );
          $headers = array('Content-Type: text/html; charset=UTF-8');
          $subject='Error detected on Gmail attachment upload';
          $message='Dear admin, </br></br>Following error has been detected while uploading Gmail attachment to woocommerce order:</br>' . $error;
          wp_mail( $admin_email, $subject, $message, $headers );
      }
  }
  
  //API function to get messages
  function listMessages($service, $userId) {
      $pageToken = NULL;
      $messages = array();
      $opt_param = array();
      try {
          $opt_param['includeSpamTrash'] = FALSE;
          $opt_param['labelIds'] = 'INBOX';
          $opt_param['maxResults'] = 1000;
          $opt_param['q'] = 'from:han@nerb.com.sg';
          $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
          if ($messagesResponse->getMessages()) {
              $messages = array_merge($messages, $messagesResponse->getMessages());
              $pageToken = $messagesResponse->getNextPageToken();
          }
      } catch (Exception $e) {
        //Way to catch error, print in log and email the error
        $file_path=dirname(__FILE__) . '/error_log.txt';
        $current_date='[' .date('Y-m-d H:i:s') .'] ';
        $error=$current_date .'An error occurred while accessing API: ' . $e->getMessage() . '\\n';
        $file= fopen($file_path,'w');
        fwrite($file,$error);
        fclose($file);
        $admin_email = get_option( 'gmail_error_email' );
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $subject='Error detected on Gmail attachment upload';
        $message='Dear admin, </br></br>Following error has been detected while uploading Gmail attachment to woocommerce order:</br>' . $error;
        wp_mail( $admin_email, $subject, $message, $headers );
      }  
      return $messages;
  }
  
  //Cron function to fetch message, check if there is order id with the details and upload files if all conditions are matched
  function main($client) {
      global $wpdb;
      $service = new Google_Service_Gmail($client);
  
      $user = 'me';
      $messages = listMessages($service, $user);
      foreach ($messages as $message) {
          $message_content = getMessage($service, $user, $message->getId());
          $payload = $message_content->getPayLoad();
          $body = $payload->getBody();
          $headers = $payload->getHeaders();
          $parts = $payload->getParts();

          foreach($headers as $single) {
            if ($single->getName() == 'Subject') {
                $message_subject = $single->getValue();
            } elseif ($single->getName() == 'Date') {
                $message_date = $single->getValue();
                $message_date = date('Y-m-d H:i:s', strtotime($message_date));
            }
        }
          //$body = $parts[0]['body'];
          $rawData = $body->data;
          $sanitizedData = strtr($rawData,'-_', '+/');
          $decodedMessage = base64_decode($sanitizedData);
          $match_arr = array();
          if(!$FOUND_BODY) {
              $parts = $payload->getParts();
              foreach ($parts  as $part) {
                  if($part['body'] && $part['mimeType'] == 'text/html') {
                      $FOUND_BODY = decodeBody($part['body']->data);
                      break;
                  }
              }
          } if(!$FOUND_BODY) {
              foreach ($parts  as $part) {
                  // Last try: if we didn't find the body in the first parts, 
                  // let's loop into the parts of the parts (as @Tholle suggested).
                  if($part['parts'] && !$FOUND_BODY) {
                      foreach ($part['parts'] as $p) {
                          // replace 'text/html' by 'text/plain' if you prefer
                          if($p['mimeType'] === 'text/html' && $p['body']) {
                              $FOUND_BODY = decodeBody($p['body']->data);
                              break;
                          }
                      }
                  }
                  if($FOUND_BODY) {
                      break;
                  }
              }
          }
  
        //   $mpmatch = '!<td style="vertical-align:top;padding:5pt 4pt;overflow:hidden"><p dir="ltr" style="line-height:1.38;margin-top:6pt;margin-bottom:0pt"><span style="font-size:11pt;font-family:Arial;background-color:transparent;font-variant-numeric:normal;font-variant-east-asian:normal;vertical-align:baseline;white-space:pre-wrap">(.*?)</span></p></td>!is';
        //   preg_match_all($mpmatch, $FOUND_BODY, $result);
        //   foreach ($result as $val) {
        //       array_push($match_arr,$val);
          
        //   }
       
          $FOUND_BODY = str_replace("\xc2\xa0", '',strip_tags($FOUND_BODY));
          $FOUND_BODY = str_replace('Product ','Product',$FOUND_BODY);
          $FOUND_BODY = str_replace('Product ','Product',$FOUND_BODY);
          $FOUND_BODY = str_replace('From ','From',$FOUND_BODY);
          $FOUND_BODY = str_replace('Policyholder ','Policyholder',$FOUND_BODY);
          $FOUND_BODY = str_replace('Policyholder ','Policyholder',$FOUND_BODY);
          $FOUND_BODY = str_replace('Period of Cover ','Period of Cover',$FOUND_BODY);

          $product = trim(get_string_between($FOUND_BODY,'Product','Policy Number'));
          $policy_holder = trim(get_string_between($FOUND_BODY,'Policyholder','Period of Cover'));
          $period_of_cover = get_string_between($FOUND_BODY,'From:','(Both dates inclusive)');
          if($period_of_cover=='')
          {
              $period_of_cover = get_string_between($FOUND_BODY,'From:','(both dates inclusive)');
          }
          $new_period_arr = explode(' To ',$period_of_cover);
          $coverage_start = date('Ymd',strtotime(str_replace('/','-',$new_period_arr[0])));
          $product = trim(str_replace(':','',$product));
          $policy_holder = trim(str_replace(':','',$policy_holder));
        //   echo $product . '<br>';
        //   echo $policy_holder . '<br>';
        //   echo $period_of_cover . '<br>';
        //   echo $coverage_start . '<br>';
        //   echo $FOUND_BODY;
        //    var_dump($match_arr);
          //exit;
        //   $count = count($match_arr[1]);
        //   if($count != 0)
        //   {
        //       for($i=0;$i<$count;$i++)
        //       {
        //           if(trim($match_arr[1][$i])=='Product')
        //           {
        //               $product = trim($match_arr[1][$i+1]);
        //               //echo $product;
        //           }
        //           else if(trim($match_arr[1][$i])=='Period of Cover')
        //           {
        //               $period_of_cover = trim($match_arr[1][$i+1]);
        //           }
        //       }
        //   }
        //   $match_arr = array();
        //   $mpmatch = '!<td style="vertical-align:top;padding:5pt 4pt;overflow:hidden"><p dir="ltr" style="line-height:1.38;text-align:justify;margin-top:6pt;margin-bottom:0pt"><span style="font-size:11pt;font-family:Arial;background-color:transparent;font-variant-numeric:normal;font-variant-east-asian:normal;vertical-align:baseline;white-space:pre-wrap">(.*?)</span></p></td>!is';
        //   preg_match_all($mpmatch, $FOUND_BODY, $result);
        //   foreach ($result as $val) {
        //       array_push($match_arr,$val);
          
        //   }
        //   //var_dump($match_arr);
        //   $policy_holder = trim($match_arr[1][0]);
        //   //$product = 'initial ' . $product;
        //   $product = str_replace("\xc2\xa0", '',$product);
          $prefix = $wpdb->prefix;    
          $table_name = $prefix . "gmail_product_variation";
          $product_result = $wpdb->get_results('SELECT woocommerce_product_name FROM ' . $table_name . ' WHERE email_product_name = "'.$product.'"','ARRAY_N');
          if(!empty($product_result))
          {
            $product_name = $product_result[0][0];
          }
          else{
              $product_name = $product;
          }
          //exit;
        //   $new_period = str_replace('From:','',$period_of_cover);
        //   $new_period = str_replace(' (Both dates inclusive)','',$new_period);
        //   $new_period_arr = explode(' To ',$new_period);
  
        //   $coverage_start = date('Ymd',strtotime(substr(str_replace('/','-',$new_period_arr[0]),3)));
          
          $table_name = $wpdb->prefix . 'postmeta';
          $output = $wpdb->get_results('SELECT post_id FROM ' . $table_name . ' a INNER JOIN '.$wpdb->prefix.'posts b ON a.post_id = b.ID WHERE a.meta_key="coverage_start" AND a.meta_value="'.$coverage_start.'" AND b.post_type="shop_order"');
          if(!empty($output))
          {
              $num = 0;
              foreach($output as $o)
              {
                  $order_id = $o->post_id;
                  //echo $order_id . '<br>';
                  $order = wc_get_order($order_id);
                  $password = $order->get_meta( '_billing_company_uen' );
                  //echo $password . '<br>';
                //   exit;
                  $product_new = get_page_by_title( $product_name, OBJECT, 'product' );
                  $product_id = $product_new->ID;
                  $item_data = $wpdb->get_results('SELECT a.order_item_id  FROM ' . $wpdb->prefix . 'woocommerce_order_itemmeta a INNER JOIN ' . $wpdb->prefix . 'woocommerce_order_items b ON a.order_item_id = b.order_item_id WHERE b.order_id ="'.$order_id.'" AND a.meta_key="_product_id" AND meta_value="'.$product_id.'"','ARRAY_N');
                  if(strtolower(str_replace('.','',get_post_meta($order_id,'_billing_company',true))) == strtolower(str_replace('.','',$policy_holder)))
                  {
                      $main_folder = dirname(dirname(dirname( __FILE__ ))) . '/wc_order_uploads/' .  $order_id . '/';
                      if(file_exists($main_folder))
                      {
                          wp_mkdir_p( $main_folder );
                          $attachments = getAttachments($message->getId(),$parts, $service);
                  
                          foreach($attachments as $a){
                              $filename = pathinfo($a['filename'], PATHINFO_FILENAME);
                              $file_extension = pathinfo($a['filename'], PATHINFO_EXTENSION);
                              $file_folder =$main_folder .  $item_data[0][0] . '_' . str_replace(' ','-',strtolower($product_name)) . '/';
                              if(file_exists($file_folder))
                              {
                                  if(file_exists($file_folder .  $a['filename']))
                                  {
  
                                  }
                                  else{
                                      file_put_contents($file_folder .  $a['filename'], base64_decode(str_replace(array('-', '_'), array('+', '/'), $a['data'])));
                                      $file_extension = pathinfo($a['filename'], PATHINFO_EXTENSION);
                                       
                                     $file_arr = array();
                                      if($file_extension == 'zip')
                                      {
                                          $zip = new ZipArchive;
                                          $res = $zip->open($file_folder .  $a['filename']);
                                          if($res == true)
                                          {
                                            $zip->setPassword($password);
                                            for($i = 0; $i < $zip->numFiles; $i++) {
                                                $filename ='';
                                                $filename = $zip->getNameIndex($i);
                                                
                                               
                                                
                                                if(strpos(strtoupper($filename),'DEBIT NOTE')==false)
                                                {
                                                    array_push($file_arr,$filename);
                                                // $fileinfo = pathinfo($filename);
                                                
                                                // copy("zip://".$file_folder .  $a['filename']."#".$filename, $file_folder .  $fileinfo['basename']);
                                                }
                                            }  
                                            
                                            $zip->extractTo($file_folder,$file_arr);          
                                            $zip->close();
                                            wp_delete_file($file_folder .  $a['filename']);
                                            //exit;
                                          }
                                      }
                                  }
                              }
                              else{
                                  wp_mkdir_p( $file_folder );
                                  $file_extension = pathinfo($a['filename'], PATHINFO_EXTENSION);
                                  file_put_contents($file_folder .  $a['filename'], base64_decode(str_replace(array('-', '_'), array('+', '/'), $a['data'])));
                            
                                  if($file_extension == 'zip')
                                  {
                                      $zip = new ZipArchive;
                                      $res = $zip->open($file_folder .  $a['filename']);
                                      if($res == true)
                                      {
                                        $zip->setPassword($password);
                                        for($i = 0; $i < $zip->numFiles; $i++) {
                                            $filename ='';
                                            $filename = $zip->getNameIndex($i);
                                            
                                           
                                            
                                            if(strpos(strtoupper($filename),'DEBIT NOTE')==false)
                                            {
                                                array_push($file_arr,$filename);
                                            // $fileinfo = pathinfo($filename);
                                            
                                            // copy("zip://".$file_folder .  $a['filename']."#".$filename, $file_folder .  $fileinfo['basename']);
                                            }
                                        }  
                                        
                                        $zip->extractTo($file_folder,$file_arr);          
                                        $zip->close();
                                        wp_delete_file($file_folder .  $a['filename']);
                                        //exit;
                                      }
                                  }
                              }
                          }
                      }
                      else{
                          wp_mkdir_p( $main_folder );
                          $attachments = getAttachments($message->getId(),$parts, $service);
                  
                          foreach($attachments as $a){
                              $filename = pathinfo($a['filename'], PATHINFO_FILENAME);
                              $file_folder =$main_folder .  $item_data[0][0] . '_' . str_replace(' ','-',strtolower($product_name)) . '/';
                              wp_mkdir_p( $file_folder );
                              $file_extension = pathinfo($a['filename'], PATHINFO_EXTENSION);
                             
                              
                              file_put_contents($file_folder .  $a['filename'], base64_decode(str_replace(array('-', '_'), array('+', '/'), $a['data'])));
                               if($file_extension == 'zip')
                              {
                                  $zip = new ZipArchive;
                                  $res = $zip->open($file_folder .  $a['filename']);
                                  if($res == true)
                                  {
                                    $zip->setPassword($password);
                                    for($i = 0; $i < $zip->numFiles; $i++) {
                                        $filename ='';
                                        $filename = $zip->getNameIndex($i);
                                        
                                       
                                        
                                        if(strpos(strtoupper($filename),'DEBIT NOTE')==false)
                                        {
                                            array_push($file_arr,$filename);
                                        // $fileinfo = pathinfo($filename);
                                        
                                        // copy("zip://".$file_folder .  $a['filename']."#".$filename, $file_folder .  $fileinfo['basename']);
                                        }
                                    }  
                                    
                                    $zip->extractTo($file_folder,$file_arr);          
                                    $zip->close();
                                    wp_delete_file($file_folder .  $a['filename']);
                                    //exit;
                                  }
                              }
                          }
                          
                      }
                      $num++;
                  }
                  else
                    {
                        if($num==0){
                        $file_path=dirname(__FILE__) . '/error_log.txt';
                        $current_date='[' .date('Y-m-d H:i:s') .'] ';
                        $error=$current_date .'An error occurred while uploading: No match for email with order, Email details: <Br>Email subject: ' . $message_subject . '<br>Email date: ' . $message_date .'\\n';
                        $file= fopen($file_path,'w');
                        fwrite($file,$error);
                        fclose($file);
                        $admin_email = get_option( 'gmail_error_email' );
                        $headers = array('Content-Type: text/html; charset=UTF-8');
                        $subject='Error detected on Gmail attachment upload';
                        $message_new='Dear admin, </br></br>Following error has been detected while uploading Gmail attachment to woocommerce order:</br> No match for email with order, Email details: <Br>Email subject: ' . $message_subject . '<br>Email date: ' . $message_date;
                        wp_mail( $admin_email, $subject, $message_new, $headers );
                       
                        $num++;
                        }
                    }
                }
          }
          else
          {
            $file_path=dirname(__FILE__) . '/error_log.txt';
            $current_date='[' .date('Y-m-d H:i:s') .'] ';
            $error=$current_date .'An error occurred while uploading: No match for email with order, Email details: <Br>Email subject: ' . $message_subject . '<br>Email date: ' . $message_date .'\\n';
            $file= fopen($file_path,'w');
            fwrite($file,$error);
            fclose($file);
            $admin_email = get_option( 'gmail_error_email' );
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $subject='Error detected on Gmail attachment upload';
            $message_new='Dear admin, </br></br>Following error has been detected while uploading Gmail attachment to woocommerce order:</br> No match for email with order, Email details: <Br>Email subject: ' . $message_subject . '<br>Email date: ' . $message_date;
            
            wp_mail( $admin_email, $subject, $message_new, $headers );
          }
          
          $FOUND_BODY='';
      }
    
  }
  
add_action('init', 'gw_activation');

//Function to find the current selected schedule for cron and update if it is different then current applied 
function gw_activation() {
    if(!get_option('gmail_cron_settings'))
    {
        add_option('gmail_cron_settings','1 hour');
    }
        $hour_value = get_option('gmail_cron_settings');

        if(wp_get_schedule('gw_hourly_event_for_upload') != false)
        {
            $schedule = wp_get_schedule('gw_hourly_event_for_upload');
            if($schedule=='hourly')
            {
                if($hour_value!='1 hour')
                {
                    wp_clear_scheduled_hook('gw_hourly_event_for_upload');
                    if($hour_value=='3 hours')
                    {
                        wp_schedule_event(time(), 'three_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='6 hours')
                    {
                        wp_schedule_event(time(), 'six_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='12 hours')
                    {
                        wp_schedule_event(time(), 'twelve_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value =='1 day')
                    {
                        wp_schedule_event(time(), 'daily', 'gw_hourly_event_for_upload');
                    }
                    
                }
            }
            elseif($schedule=='three_hours'){
                if($hour_value!='3 hours')
                {
                    wp_clear_scheduled_hook('gw_hourly_event_for_upload');
                    if($hour_value=='1 hour')
                    {
                        wp_schedule_event(time(), 'hourly', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='6 hours')
                    {
                        wp_schedule_event(time(), 'six_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='12 hours')
                    {
                        wp_schedule_event(time(), 'twelve_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value =='1 day')
                    {
                        wp_schedule_event(time(), 'daily', 'gw_hourly_event_for_upload');
                    }
                    
                }
            }
            elseif($schedule=='six_hours'){
                if($hour_value!='6 hours')
                {
                    wp_clear_scheduled_hook('gw_hourly_event_for_upload');
                    if($hour_value=='1 hour')
                    {
                        wp_schedule_event(time(), 'hourly', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='3 hours')
                    {
                        wp_schedule_event(time(), 'three_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='12 hours')
                    {
                        wp_schedule_event(time(), 'twelve_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value =='1 day')
                    {
                        wp_schedule_event(time(), 'daily', 'gw_hourly_event_for_upload');
                    }
                    
                }
            }
            elseif($schedule=='twelve_hours'){
                if($hour_value!='12 hours')
                {
                    wp_clear_scheduled_hook('gw_hourly_event_for_upload');
                    if($hour_value=='1 hour')
                    {
                        wp_schedule_event(time(), 'hourly', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='6 hours')
                    {
                        wp_schedule_event(time(), 'six_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='3 hours')
                    {
                        wp_schedule_event(time(), 'three_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value =='1 day')
                    {
                        wp_schedule_event(time(), 'daily', 'gw_hourly_event_for_upload');
                    }
                    
                }
            }
            elseif($schedule=='daily'){
                if($hour_value!='1 day')
                {
                    wp_clear_scheduled_hook('gw_hourly_event_for_upload');
                    if($hour_value=='1 hour')
                    {
                        wp_schedule_event(time(), 'hourly', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='6 hours')
                    {
                        wp_schedule_event(time(), 'six_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value=='12 hours')
                    {
                        wp_schedule_event(time(), 'twelve_hours', 'gw_hourly_event_for_upload');
                    }
                    elseif($hour_value =='3 hours')
                    {
                        wp_schedule_event(time(), 'three_hours', 'gw_hourly_event_for_upload');
                    }
                    
                }
            }
        }
        elseif (! wp_next_scheduled ( 'gw_hourly_event_for_upload' )) {
        wp_schedule_event(time(), 'hourly', 'gw_hourly_event_for_upload');
        }
}

add_action('gw_hourly_event_for_upload', 'cron_run_upload');
//add_action('admin_init', 'cron_run_upload');

//Main cron function to first connect to google API and then run 'main' function for further process
  function cron_run_upload(){
    require_once dirname(__FILE__) .'/google_api/vendor/autoload.php';

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
  // create Client Request to access Google API
  $client = new Google_Client();
  $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
  $client->setAuthConfig(dirname(__FILE__) .'/config.json');
  
  //current code, to change in future
  //$code = '4/0AX4XfWj25uBIh1elMxcEnbAp0X7uyZIXc1vHL_ItUhFVnX2e88N33yVJ-VVdIWc3qTivCQ';
  
  //$refresh_code = '1//048eL8_p617gLCgYIARAAGAQSNwF-L9IrUnD6pY6oRP1teYbLE8xehFWUqxttrB_ay8eG35qckR_Pdh9FUQGJ9U0ZdmS6jmjoXhM';

  //dhanashree.dattaram.sawant@gmail.com refresh code
  //$refresh_code= '1//04cEuldl7tkQlCgYIARAAGAQSNwF-L9Ir7OL6KahnNYIB4Q0A0yo1TABgeaQKoVF8OrF59uWvEdl_4ITf-eoi1OxIU3OIKUKHy3Q'; 
  //New refresh code
  $refresh_code = '1//04hqDO00Jy5fcCgYIARAAGAQSNwF-L9IrosVHVEzfRNJjRANHgxliIBMGqWHcTH-eJ6FKAoJLS4srE04jPh5XSE95N5ELsbMhQhg';
  // authenticate code from Google OAuth Flow
  if(isset($refresh_code)) {
      $accessToken = $client->fetchAccessTokenWithRefreshToken($refresh_code);
      $_SESSION['token'] = $accessToken;
if(isset($accessToken['error']))
{
    $file_path=dirname(__FILE__) . '/error_log.txt';
            $current_date='[' .date('Y-m-d H:i:s') .'] ';
            $error=$current_date .'An error occurred while accessing API: ' . $accessToken['error_description'] . '\\n';
            $file= fopen($file_path,'w');
            fwrite($file,$error);
            fclose($file);
            $admin_email = get_option( 'gmail_error_email' );
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $subject='Error detected on Gmail attachment upload';
            $message='Dear admin, </br></br>Following error has been detected while uploading Gmail attachment to woocommerce order:</br>' . $error;
            wp_mail( $admin_email, $subject, $message, $headers );
}
      $client->setAccessToken($accessToken);
      main($client);
  } else {
      echo "<a href='".$client->createAuthUrl()."'>Google Login</a>";
  }
}
  
//API function to fetch attachments from message
  function getAttachments($message_id, $parts,$service) {
      $attachments = [];
      foreach ($parts as $part) {
          if (!empty($part->body->attachmentId)) {
              $attachment = $service->users_messages_attachments->get('me', $message_id, $part->body->attachmentId);
              $attachments[] = [
                  'filename' => $part->filename,
                  'mimeType' => $part->mimeType,
                  'data'     => $attachment->data
              ];
          } else if (!empty($part->parts)) {
              $attachments = array_merge($attachments, getAttachments($message_id, $part->parts,$service));
          }
      }
      return $attachments;
  }
  
  add_filter( 'cron_schedules', 'gw_add_cron_interval' );

  //Function to specify new schedules for cron
function gw_add_cron_interval( $schedules ) { 
    $schedules['three_hours'] = array(
        'interval' => 3 * 60 * 60,
        'display'  => esc_html__( 'Every three hours' ), );
        $schedules['six_hours'] = array(
            'interval' => 6 * 60 * 60,
            'display'  => esc_html__( 'Every six hours' ), );
            $schedules['twelve_hours'] = array(
                'interval' => 12 * 60 * 60,
                'display'  => esc_html__( 'Every twelve hours' ), );
    return $schedules;
}

//Main function to display content on admin page of plugin
  add_action('admin_menu', 'gw_custom_menu');
    function gw_custom_menu() { 
     
      add_menu_page( 
        'Gmail to Woocommerce', 
        'Gmail to Woocommerce', 
        'edit_posts', 
        'gmail_to_woocommerce', 
        'gw_custom_function', 
        'dashicons-groups',
        56 
       
         );
    }
      function gw_custom_function(){
        global $wpdb;
        $prefix = $wpdb->prefix;
      
        $product_variation_table = $prefix . "gmail_product_variation";
        $output = '<h2 class="gmail_plugin_header">Note: Email attachments with word "Debit Note" will not be uploaded to Woocommerce</h2>';

        //change cron
        if(!get_option('gmail_cron_settings'))
        {
            add_option('gmail_cron_settings','1 hour');
        }
        $hour_value = get_option('gmail_cron_settings');

        $output .= '<form class="gmail_to_woo_form" method="post" enctype="multipart/form-data">';

        $output .='<h2 class="gmail_plugin_header">Change cron schedule:</h2>';
        if($hour_value=='1 hour')
        {
            $output .= '<select name="cron_select"><option value="1 hour" selected>Every 1 hour</option><option value="3 hours">Every 3 hours</option><option value="6 hours">Every 6 hours</option><option value="12 hours">Every 12 hours</option><option value="1 day">Every day</option></select>';
        }
        elseif($hour_value=='3 hours')
        {
            $output .= '<select name="cron_select"><option value="1 hour">Every 1 hour</option><option value="3 hours" selected>Every 3 hours</option><option value="6 hours">Every 6 hours</option><option value="12 hours">Every 12 hours</option><option value="1 day">Every day</option></select>';
        }
        elseif($hour_value=='6 hours')
        {
            $output .= '<select name="cron_select"><option value="1 hour">Every 1 hour</option><option value="3 hours">Every 3 hours</option><option value="6 hours" selected>Every 6 hours</option><option value="12 hours">Every 12 hours</option><option value="1 day">Every day</option></select>';
        }
        elseif($hour_value=='12 hours')
        {
            $output .= '<select name="cron_select"><option value="1 hour">Every 1 hour</option><option value="3 hours">Every 3 hours</option><option value="6 hours">Every 6 hours</option><option value="12 hours" selected>Every 12 hours</option><option value="1 day">Every day</option></select>';
        }
        elseif($hour_value == '1 day')
        {
            $output .= '<select name="cron_select"><option value="1 hour">Every 1 hour</option><option value="3 hours">Every 3 hours</option><option value="6 hours">Every 6 hours</option><option value="12 hours">Every 12 hours</option><option value="1 day" selected>Every day</option></select>';
        }
        $output .= '<input type="submit" name="cron_submit" value="update">';

        if(isset($_POST['cron_submit'])){
            $schedule_value=$_POST['cron_select'];
            update_option('gmail_cron_settings',$schedule_value);
            $success_message='<h4>Cron Schedule has been updated successfully.</h4>';
        }
        $output .=$success_message;
        $output .='<h2 class="gmail_plugin_header">Download updated error log file related to API and upload:</h2>';
        $download_link=get_site_url() . '/wp-content/plugins/gmail-to-woocommerce/error_log.txt';
        $output .='<a href="'.$download_link.'" download>Download</a>';
        $output .='<h2 class="gmail_plugin_header">Update email to send error:</h2>';
        if(!get_option('gmail_error_email'))
        {
            $admin_email = get_option( 'admin_email' );
            add_option('gmail_error_email',$admin_email);
        }
        $error_email = get_option('gmail_error_email');
        $output .='<span>Error email: <input type="text" name="error_email" value="'.$error_email.'"><input type="submit" name="error_email_submit" value="save"></span>';
        $output .='<h2 class="gmail_plugin_header">Add or delete product mapping:</h2>';
        $output .='<span style="margin-right:20px">Email product name: <input type="text" name="email_product_name"></span><span>Woocommerce equivalent product name: <input type="text" name="woo_product_name"><input type="submit" name="product_map_submit" value="save"></span>';
        $output .='<table class="product_map_table"><thead><tr><th>Email product name</th><th>Woocommerce product name</th><th>Delete link</th></tr></thead><tbody>';
        $results = $wpdb->get_results('SELECT * FROM '. $product_variation_table);
        if(!empty($results)){
            foreach($results as $r){
                $email_product_name=$r->email_product_name;
                $woocommerce_product_name=$r->woocommerce_product_name;
                $id=$r->ID;
                $output .='<tr><td>'.$email_product_name.'</td><td>'.$woocommerce_product_name.'</td><td><input type="submit" name="delete_id" value="Delete (entry id:'.$id.')"></td></tr>';
            }
        }
        $output .='</tbody></table>';

        if(isset($_POST['product_map_submit']))
        {
            $email_pro_name = $_POST['email_product_name'];
            $woo_pro_name = $_POST['woo_product_name'];
            $wpdb->insert($product_variation_table,array('email_product_name'=>$email_pro_name,'woocommerce_product_name'=>$woo_pro_name));
            wp_redirect($_SERVER['HTTP_REFERER']);
        }
        if(isset($_POST['delete_id']))
        {
            $delete_id = str_replace('Delete (entry id:','',$_POST['delete_id']);
            $delete_id = str_replace(')','',$delete_id);
            $wpdb->query('DELETE FROM ' . $product_variation_table . ' WHERE ID="'.$delete_id.'"');
            wp_redirect($_SERVER['HTTP_REFERER']);
        }
        if(isset($_POST['error_email_submit']))
        {
            $error_email = $_POST['error_email'];
            update_option('gmail_error_email',$error_email);
        }
        $output .='</form>';
        echo $output;
      }

      function gw_create_plugin_database_table()
      {
      global $wpdb;
      
      $charset_collate = $wpdb->get_charset_collate();
      
      $prefix = $wpdb->prefix;
      
      $table_name = $prefix . "gmail_product_variation";
      
      
      $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `email_product_name` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `woocommerce_product_name` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        PRIMARY KEY (`ID`)
       )    $charset_collate;";
      
      
      
      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      dbDelta( $sql );
      }
      
      add_action( 'admin_init', 'gw_create_plugin_database_table' );

function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

