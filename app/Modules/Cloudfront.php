<?php

namespace WpRloutHtml\Modules;

use Aws\CloudFront\CloudFrontClient;

Class Cloudfront {

	// Verifica se recebeu atalho para limpar cloudfront em /*
	public function __construct(){
		
		if(isset($_GET['cloudfront_rlout'])){
			
			$response_cloudfront = Cloudfront::invalid('/*');
			if($response_cloudfront){
				echo '<script>alert("Cloudfront Atualizados!");</script>';
				echo '<script>window.location = document.URL.replace("&cloudfront_rlout=true","").replace("?cloudfront_rlout=true","");</script>';
			}
		}
	}

    static function invalid($response){
        // debug_print_backtrace();
        $DistributionId = get_option('s3_distributionid_rlout');

		if(!empty($DistributionId)){
			$CallerReference = (string) rand(100000,9999999).strtotime(date('Y-m-dH:i:s'));
			$raiz = str_replace(site_url(), '', $response);
			
			$access_key = get_option('s3_key_rlout');
			$secret_key = get_option('s3_secret_rlout');
			$acl_key = get_option('s3_acl_rlout');
			$region = get_option('s3_region_rlout');
			
			try{
				$cloudFrontClient = new CloudFrontClient([
					'region' => 'us-east-1',
					'version' => 'latest',
					'credentials' => [
						'key'    => $access_key,
						'secret' => $secret_key,
					]
				]);

				$args = [
					'DistributionId' => $DistributionId,
					'InvalidationBatch' => [
						'CallerReference' => $CallerReference,
						'Paths' => [
							'Items' => [$raiz],
							'Quantity' => 1,
						],
					]
				];
				
				$result = $cloudFrontClient->createInvalidation($args);
			}
			catch(\Aws\CloudFront\Exception\CloudFrontException $e) {
				die($e);
			}
			

			// $result = $cloudFrontClient->listDistributions([]);
			// die(var_dump($result));
			// $result = $cloudFrontClient->listInvalidations(['DistributionId'=>$DistributionId]);

			
			return $result;
		}
    }
}