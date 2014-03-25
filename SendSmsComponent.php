<?php
/**
 * This SMS component was put together from an original CakePHP component 
 * created by the author Doug Bromley in 2008 for the CakePHP framework.
 * 
 * It has been successfully used in Yii Versions: 1.1.5-1.1.15
 *
 * @author Doug Bromley <doug.bromley@gmail.com>
 * @link https://github.com/OdinsHat/yii-clickatell-smscomponent
 * @license BSD
 * 
 * @todo Make better use of Yii framework integration
 * @todo Improve documentation
 * @todo Improve error handling
 * @todo a spring clean
 */
 
class SendSmsComponent extends CApplicationComponent
{
    /**
     * The username for the Clickatell API
     * @var string
     */
    public $api_user;

    /**
     * The password for the Clickatell API
     * @var string
     */
    public $api_pass;

    /**
     * Who will be shown as the sender of the text at the receivers handset.
     * @var string
     */
    public $api_from;

    /**
     * The API id for this product.
     * @var string
     */
    public $api_id;

    /**
     * The delay in minutes before the message is sent to the reciever.
     * This doesn't affect the speed of the script execution - its a variable
     * used at the Clickatell end to delay message sending.
     * 
     * @access public
     * @var integer
     */
    public $delivery_delay = null;

    /**
     * If the text is delayed then switching this on "1"
     * will cause it to be escalated to and alternative.
     * Could cost more to send the message!
     * 
     * @access public
     * @var integer
     */
    public $escalate = 0;

    public $session_id = null;

    public $batch_id = 0;

    public $errors = array();

    public $last_response = '';

    public $last_recipient = '';

    public $batch_template = '';

    public $recipients = array();

    public $company = '';

    public $branch = null;

    /**
     * The Clickatell XML API url
     */
    const API_XML_URL = 'http://api.clickatell.com/xml/xml';

    /**
     * The Clickatell HTTP API url for sending GET or POST requests too.
     */
    const API_HTTP_URL = 'http://api.clickatell.com/http/';
    
    /**
     * The Clickatell HTTP Batch API url for sending batch messages
     */
    const API_HTTP_BATCH_URL = 'http://api.clickatell.com/http_batch/';

    
    public function __construct($api_user = null, $api_pass = null, $api_from = null, $api_id = null)
    {
        $this->api_user = $api_user;
        $this->api_pass = $api_pass;
        $this->api_from = $api_from;
        $this->api_id = $api_id;
    }

    public function init()
    {
        parent::init();
    }

    /**
     * Authenticate and retrieve a session id for a batch sending job.
     *
     * Single texts simply take the credentials in the post data request with 
     * the message. But batch messages require the session id given by this 
     * method to send a batch of messages.
     *
     * @return integer
     */
    public function auth()
    {
        $postdata = http_build_query(
            array(
                'api_id' => $this->api_id,
                'user' => $this->api_user,
                'password' => $this->api_pass
            )
        );

        $opts = array('http' => 
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);
        $response = file_get_contents(self::API_HTTP_URL.'auth', false, $context);
        if(empty($response)){
            Yii::log('Empty response from API service');
            throw new CException('Empty response from API service');
        }
        $this->last_response = $response;
        $this->session_id = str_replace('OK: ', '', $response);

        return $response;
    }
    
    /**
     * Post a message to the Clickatell servers for the number provided
     * 
     * @param string $tel The telephone number in international format.  Not inclduing a leading "+" or "00".
     * @param string $message The text message to send to the handset.
     * @return string
     * 
     * @see SendSmsComponent::api_id
     * @see SendSmsComponent::api_user
     * @see SendSmsComponent::api_pass
     * @see SendSmsComponent::api_from
     */
    public function postSms($tel, $message, $company = 'MLink') {

        $data = array(
            'api_id' => $this->api_id,
            'user' => $this->api_user,
            'password' => $this->api_pass,
            'from' => $company,
            'to' => $tel,
            'text' => $message,
            'deliv_time' => $this->delivery_delay,
            'escalate' => $this->escalate
        );

        $context  = $this->buildContext($data);
        $response = file_get_contents(self::API_HTTP_URL.'sendmsg', false, $context);
        if(empty($response)){
            Yii::log('Empty response from API service');
            throw new CException('Empty response from API service');
        }
        return $response;
    }

    /**
     * By providing a phone number you can check if that number is covered and a message can be sent
     * to this network/prefix/number.
     * @param string $number The telephone number to check for coverage
     * @return string
     */
    public function queryCoverage($number) {
        $data = array(
            'api_id' => $this->api_id,
            'user' => $this->api_user,
            'password' => $this->api_pass,
            'msisdn' => $number
        );

        $context  = $this->buildContext($data);
        $response = file_get_contents(self::API_HTTP_URL.'routeCoverage.php', false, $context);
        if(empty($response)){
            throw new CException('Empty response from API service');
        }
        return $response;
    }

    /**
     * Begin a batch sending session
     *
     * Its important the template contains #field1# style tags. These will 
     * then get replaced with the field data.
     *
     * @param string $template the messaging template to use
     * @param object $company a user model object of the branch to send from
     * @param array $recipients array of customer objects to text
     * @param integer $ack an integer boolean value whether to send acknowledgement
     * @return string
     */
    public function startBatch($template, $company, $recipients, $ack = 1)
    {
        $this->company = $company->company;
        $this->branch = $company;
        $this->batch_template = $template;
        $this->recipients = $recipients;
        $from = $company->company;

        $data = array(
            'session_id' => $this->session_id,
            'template' => $this->batch_template,
            'from' => $from
        );
        $context = $this->buildContext($data);
        $response = file_get_contents(self::API_HTTP_BATCH_URL.'startbatch', false, $context);

        if(empty($response)){
            throw new CException('Empty response from API service');
        }
        $this->last_response = $response;
        if(strpos($response, 'ERR') === FALSE){
            $this->batch_id = str_replace('ID: ', '', $response);
            return true;
        }
        Yii::log($reponse);
        $this->errors[] = $response;
        return false;
    }

    public function sendBatchMessage()
    {
        $fields = array();
        if(empty($this->recipients)){
            Yii::log('No recipients for batch message');
            return false;
        }
        $recipient = array_pop($this->recipients);

        if(strpos($this->batch_template, '#field1#') !== FALSE){
            $fields['field1'] = $this->branch->company;
        }
        if(strpos($this->batch_template, '#field2#') !== FALSE){
            $fields['field2'] = $this->branch->phone;
        }

        $data = array(
            'session_id' => $this->session_id,
            'batch_id' => $this->batch_id,
            'to' => $recipient->mobile
        );
        $data += $fields;
        $context = $this->buildContext($data);
        $response = file_get_contents(self::API_HTTP_BATCH_URL.'senditem', false, $context);

        if(empty($response)){
            throw new CException('Empty response from API service');
        }
        $this->last_response = $response;
        $this->last_recipient = $recipient;
        if(strpos($response, 'ERR') === FALSE){
            return true;
        }
        $this->errors[] = $response;
        $this->errors[] = $data;
        return false;
    }

    /**
     * Builds a correctly formatted context for sending to the Clcikatell API 
     * enpoint in POST format.
     *
     * @param array $data to data to be formatted into valid url-encoded postdata
     *
     * @return resource
     */
    private function buildContext($data)
    {
        $postdata = http_build_query($data);

        $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
        );

        $context  = stream_context_create($opts);
        return $context;
    }

    private function mapTemplateFields($data)
    {
        $template = $this->template;
        foreach($data as $key => $val){
            $template = str_replace('#'.$key.'#', $val, $template);
        }
        return $template;
    }

    /**
     * A crude error output method.
     *
     * @todo improve error handling.
     * @todo improve error loging here.
     */
    public function printErrors()
    {
        foreach($this->errors as $error){
            echo $error;
        }

        echo $this->last_response;

        print_r($this);
    }
}
?>
