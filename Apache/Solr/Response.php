<?php
/**
 * @copyright Copyright 2007 Conduit Internet Technologies, Inc. (http://conduit-it.com)
 * @license Apache Licence, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package Apache
 * @subpackage Solr
 * @author Donovan Jimenez <djimenez@conduit-it.com>
 */

/**
 * Represents a Solr response.  Parses the raw response into a set of stdClass objects
 * and associative arrays for easy access.
 *
 * It can only work if the server returns serialized php objects
 *
 */
class Apache_Solr_Response
{
	/**
	 * Holds the raw response used in construction
	 *
	 * @var string
	 */
	protected $_rawResponse;

	/**
	 * Parsed values from the passed in http headers
	 *
	 * @var string
	 */
	protected $_httpStatus, $_httpStatusMessage, $_type, $_encoding;

	/**
	 * Whether the raw response has been parsed
	 *
	 * @var boolean
	 */
	protected $_isParsed = false;

	/**
	 * Parsed representation of the data
	 *
	 * @var mixed
	 */
	protected $_parsedData;

	/**
	 * Data parsing flags.  Determines what extra processing should be done
	 * after the data is initially converted to a data structure.
	 *
	 * @var boolean
	 */
	protected $_createDocuments = true,
			$_collapseSingleValueArrays = true;

	/**
	 * Constructor. Takes the raw HTTP response body and the exploded HTTP headers
	 *
	 * @param string $rawResponse
	 * @param array $httpHeaders
	 * @param boolean $createDocuments Whether to convert the documents instances to Apache_Solr_Document instances
	 * @param boolean $collapseSingleValueArrays Whether to make multivalued fields appear as single values
	 */
	public function __construct($rawResponse, $responseInfo = array(), $createDocuments = true, $collapseSingleValueArrays = true)
	{
		//Assume 0, 'Communication Error', utf-8, and  text/plain
		$status = 0;
		$statusMessage = 'Communication Error';
		$type = 'text/plain';
		$encoding = 'UTF-8';

		if(is_array($responseInfo) && !empty($responseInfo)) {
			if($responseInfo['errno'] != 0) {
				$statusMessage = $responseInfo['errmsg'];
			} else {
				if(isset($responseInfo['http_code'])) {
					$status = $responseInfo['http_code'];
					$statusMessage = $this->http_status_code_string($status);
				}
				if(isset($responseInfo['content_type'])) {
					//split content type value into two parts if possible
					$parts = split(';', $responseInfo['content_type'], 2);

					$type = trim($parts[0]);

					if ($parts[1])
					{
						//split the encoding section again to get the value
						$parts = split('=', $parts[1], 2);

						if ($parts[1])
						{
							$encoding = trim($parts[1]);
						}
					}
				}	
			}
		}

		$this->_rawResponse = $rawResponse;
		$this->_type = $type;
		$this->_encoding = $encoding;
		$this->_httpStatus = $status;
		$this->_httpStatusMessage = $statusMessage;
		$this->_createDocuments = (bool) $createDocuments;
		$this->_collapseSingleValueArrays = (bool) $collapseSingleValueArrays;
	}

	/**
	 * Convert a http status code to a string
	 *
	 * @param	integer	$code
	 * @return	string
	 */
	protected function  http_status_code_string($code)
	{
		// Source: http://www.compiledweekly.com/2008/12/31/php-function-http-status-code-value-as-string/
		// Source: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes

		switch( $code )
		{
			// 1xx Informational
			case 100: $string = 'Continue'; break;
			case 101: $string = 'Switching Protocols'; break;
			case 102: $string = 'Processing'; break; // WebDAV
			case 122: $string = 'Request-URI too long'; break; // Microsoft

			// 2xx Success
			case 200: $string = 'OK'; break;
			case 201: $string = 'Created'; break;
			case 202: $string = 'Accepted'; break;
			case 203: $string = 'Non-Authoritative Information'; break; // HTTP/1.1
			case 204: $string = 'No Content'; break;
			case 205: $string = 'Reset Content'; break;
			case 206: $string = 'Partial Content'; break;
			case 207: $string = 'Multi-Status'; break; // WebDAV

			// 3xx Redirection
			case 300: $string = 'Multiple Choices'; break;
			case 301: $string = 'Moved Permanently'; break;
			case 302: $string = 'Found'; break;
			case 303: $string = 'See Other'; break; //HTTP/1.1
			case 304: $string = 'Not Modified'; break;
			case 305: $string = 'Use Proxy'; break; // HTTP/1.1
			case 306: $string = 'Switch Proxy'; break; // Depreciated
			case 307: $string = 'Temporary Redirect'; break; // HTTP/1.1

			// 4xx Client Error
			case 400: $string = 'Bad Request'; break;
			case 401: $string = 'Unauthorized'; break;
			case 402: $string = 'Payment Required'; break;
			case 403: $string = 'Forbidden'; break;
			case 404: $string = 'Not Found'; break;
			case 405: $string = 'Method Not Allowed'; break;
			case 406: $string = 'Not Acceptable'; break;
			case 407: $string = 'Proxy Authentication Required'; break;
			case 408: $string = 'Request Timeout'; break;
			case 409: $string = 'Conflict'; break;
			case 410: $string = 'Gone'; break;
			case 411: $string = 'Length Required'; break;
			case 412: $string = 'Precondition Failed'; break;
			case 413: $string = 'Request Entity Too Large'; break;
			case 414: $string = 'Request-URI Too Long'; break;
			case 415: $string = 'Unsupported Media Type'; break;
			case 416: $string = 'Requested Range Not Satisfiable'; break;
			case 417: $string = 'Expectation Failed'; break;
			case 422: $string = 'Unprocessable Entity'; break; // WebDAV
			case 423: $string = 'Locked'; break; // WebDAV
			case 424: $string = 'Failed Dependency'; break; // WebDAV
			case 425: $string = 'Unordered Collection'; break; // WebDAV
			case 426: $string = 'Upgrade Required'; break;
			case 449: $string = 'Retry With'; break; // Microsoft
			case 450: $string = 'Blocked'; break; // Microsoft

			// 5xx Server Error
			case 500: $string = 'Internal Server Error'; break;
			case 501: $string = 'Not Implemented'; break;
			case 502: $string = 'Bad Gateway'; break;
			case 503: $string = 'Service Unavailable'; break;
			case 504: $string = 'Gateway Timeout'; break;
			case 505: $string = 'HTTP Version Not Supported'; break;
			case 506: $string = 'Variant Also Negotiates'; break;
			case 507: $string = 'Insufficient Storage'; break; // WebDAV
			case 509: $string = 'Bandwidth Limit Exceeded'; break; // Apache
			case 510: $string = 'Not Extended'; break;

			// Unknown code:
			default: $string = 'Unknown';  break;
		}

		return $string;
	}

	/**
	 * Get the HTTP status code
	 *
	 * @return integer
	 */
	public function getHttpStatus()
	{
		return $this->_httpStatus;
	}

	/**
	 * Get the HTTP status message of the response
	 *
	 * @return string
	 */
	public function getHttpStatusMessage()
	{
		return $this->_httpStatusMessage;
	}

	/**
	 * Get content type of this Solr response
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->_type;
	}

	/**
	 * Get character encoding of this response. Should usually be utf-8, but just in case
	 *
	 * @return string
	 */
	public function getEncoding()
	{
		return $this->_encoding;
	}

	/**
	 * Get the raw response as it was given to this object
	 *
	 * @return string
	 */
	public function getRawResponse()
	{
		return $this->_rawResponse;
	}

	/**
	 * Magic get to expose the parsed data and to lazily load it
	 *
	 * @param unknown_type $key
	 * @return unknown
	 */
	public function __get($key)
	{
		if (!$this->_isParsed)
		{
			$this->_parseData();
			$this->_isParsed = true;
		}

		if (isset($this->_parsedData[$key]))
		{
			return $this->_parsedData[$key];
		}

		return null;
	}

	/**
	 * Parse the raw response into the parsed_data array for access
	 */
	protected function _parseData()
	{
		//An alternative would be to use Zend_Json::decode(...)
		$data = unserialize($this->_rawResponse);

		//if we're configured to collapse single valued arrays or to convert them to Apache_Solr_Document objects
		//and we have response documents, then try to collapse the values and / or convert them now
		if (($this->_createDocuments || $this->_collapseSingleValueArrays) && isset($data['response']) && is_array($data['response']['docs']))
		{
			$documents = array();

			foreach ($data['response']['docs'] as $originalDocument)
			{
				if ($this->_createDocuments)
				{
					$document = new Apache_Solr_Document();
				}
				else
				{
					$document = $originalDocument;
				}

				foreach ($originalDocument as $key => $value)
				{
					//If a result is an array with only a single
					//value then its nice to be able to access
					//it as if it were always a single value
					if ($this->_collapseSingleValueArrays && is_array($value) && count($value) <= 1)
					{
						$value = array_shift($value);
					}

					$document->$key = $value;
				}

				$documents[] = $document;
			}

			$data['response']['docs'] = $documents;
		}

		$this->_parsedData = $data;
	}
}
