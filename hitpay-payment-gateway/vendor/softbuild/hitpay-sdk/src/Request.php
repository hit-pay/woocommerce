<?php

namespace HitPay;

/**
 * Class Request
 * @package HitPay
 */
class Request
{
    const API_ENDPOINT = '';

    const SANDBOX_API_ENDPOINT = '';

    const TYPE_CONTENT = '';

    /**
     * @var string
     */
    protected $privateApiKey = '';

    /**
     * @var bool
     */
    protected $isLive = false;

    private $ch;

    /**
     * List of errors - https://staging.hit-pay.com/docs.html?shell#errors
     *
     * @var string[]
     */
    protected $errors = array(
        400 => 'Bad Request -- Your request is invalid.',
        401 => 'Unauthorized -- Your API key is wrong.',
        404 => 'Not Found -- The payment request could not be found.',
        500 => 'Internal Server Error -- We had a problem with our server. Try again later.',
    );

    /**
     * Request constructor.
     * @param $privateApiKey
     * @param bool $live
     * @throws \Exception
     */
    public function __construct($privateApiKey, $live = false)
    {
        $this->privateApiKey = $privateApiKey;
        $this->isLive = $live;
    }

    /**
     * @param $type PUT, DELETE, GET, POST
     * @param $path
     * @param array $request
     * @return bool
     * @throws \Exception
     */
    protected function request($type, $path, $data = array())
    {
        $endpoint = $this->isLive ? static::API_ENDPOINT : static::SANDBOX_API_ENDPOINT;
		
		$url = $endpoint . $path;
		
		$args = [
			'method' => $type,
			'headers' => $this->getHeaders(),
			'timeout' => 60
		];
		
		if (!empty($data)) {
			$data = http_build_query($data);
			$args['body'] = $data;
		}
		
		$response = wp_remote_request($url, $args);
				
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		$this->checkError($response, $result);
		
		return $result;
    }

    protected function requestTest($type, $path, $data = array())
    {
        $endpoint = $this->isLive ? static::API_ENDPOINT : static::SANDBOX_API_ENDPOINT;
		
		$url = $endpoint . $path;
		
		$args = [
			'method' => $type,
			'headers' => $this->getHeaders(),
			'timeout' => 60
		];
		
		if (!empty($data)) {
			$data = http_build_query($data);
			$args['body'] = $data;
		}
		
		$response = wp_remote_request($url, $args);

        $httpCode = wp_remote_retrieve_response_code($response);

        $result = array();

        if ($httpCode == 200 || $httpCode == 201) {
            $result['status'] = 'success';
        } else {
			$message = $this->getError($response);
            $result['status'] = 'error';
			$result['message'] = $message;
            $result['content'] = wp_remote_retrieve_body( $response );
            $result['httpCode'] = $httpCode;
            $result['endpoint'] = $endpoint;
            $result['headers'] = $this->getHeaders();
        }

        return $result;
    }

    /**
     * @param null $response
     * @return void
     * @throws \Exception
     */
    protected function checkError($response, $result)
    {
		$httpCode = wp_remote_retrieve_response_code($response);
		
		if ( is_wp_error( $response ) ) {
			$error_message = '1: '.$response->get_error_message();
			throw new \Exception($error_message);
		} elseif (isset($result->detail)) {
            throw new \Exception('2: '.$result->detail);
        } elseif (isset($result->message)) {
            throw new \Exception('3: '.$result->message);
		} elseif ($httpCode != 200 && $httpCode != 201) {
            $message = isset($this->errors[$httpCode])
                ? $this->errors[$httpCode]
                : $httpCode. ': Failed to connect to HitPay Gateway Server, please contact us with your site server IP address.';
            throw new \Exception('4: '.$message, $httpCode);
        }
    }
	
	protected function getError($response)
    {
		$httpCode = wp_remote_retrieve_response_code($response);
		$message = '';
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			throw new \Exception($error_message);
		} elseif (isset($response->detail)) {
            $message = $response->detail;
        } elseif (isset($response->message)) {
            $message = $response->message.'.';
		} elseif ($httpCode != 200 && $httpCode != 201) {
            $message = isset($this->errors[$httpCode])
                ? $this->errors[$httpCode]
                : $httpCode. ': Failed to connect to HitPay Gateway Server, please contact us with your site server IP address.';
        }
		
		return $message;
    }

    /**
     * @return string[]
     */
    protected function getHeaders()
    {
        return [
            'Content-Type' => static::TYPE_CONTENT,
            'X-BUSINESS-API-KEY' => $this->privateApiKey,
            'X-Requested-With' => 'XMLHttpRequest'
        ];
    }
}