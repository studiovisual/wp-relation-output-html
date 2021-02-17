<?php

use AwsWp\CloudFront\CloudFrontClient;

Class CloudFrontRlout {

	public function __construct(){
		if(isset($_GET['cloudfront_rlout'])){
			
			$response_cloudfront = $this->invalidfileaws('/*');
			if($response_cloudfront){
				echo '<script>alert("Cloudfront Atualizados!");</script>';
				echo '<script>window.location = document.URL.replace("&cloudfront_rlout=true","").replace("?cloudfront_rlout=true","");</script>';
			}
		}
	}

    public function invalid($response){
        
        $DistributionId = get_option('s3_distributionid_rlout');

		if(!empty($DistributionId)){
			$CallerReference = (string) rand(100000,9999999).strtotime(date('Y-m-dH:i:s'));
			$raiz = str_replace(site_url(), '', $response);
			
			$access_key = get_option('s3_key_rlout');
			$secret_key = get_option('s3_secret_rlout');
			$acl_key = get_option('s3_acl_rlout');
			$region = get_option('s3_region_rlout');
			
			$cloudFrontClient = CloudFrontClient::factory(array(
				'region' => $region,
				'version' => '2016-01-28',
		
				'credentials' => [
					'key'    => $access_key,
					'secret' => $secret_key,
				]
			));

			// $result = $cloudFrontClient->listDistributions([]);
			// die(var_dump($result));
			// $result = $cloudFrontClient->listInvalidations(['DistributionId'=>$DistributionId]);

			$args = [
				'DistributionId' => $DistributionId,
				'CallerReference' => $CallerReference,
				'Paths' => [
					'Quantity' => 1,
					'Items' => [$raiz],
				],
			];
			
			$result = $cloudFrontClient->createInvalidation($args);

			return $result;
		}
    }
}