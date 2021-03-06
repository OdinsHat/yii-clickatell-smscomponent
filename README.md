Yii Clickatell SmsComponent
===========================

A component for the Yii framework using the [Clickatell](http://www.clickatell.co.uk/) HTTP API.

Able to send single SMS or start a batch session and send batch text messages.
See the Clickatell HTTP API documentation for further details on what this entails.

Installation
------------

### 1. Copy SendSmsComponent.php
Copy the main `SendSmsComponent.php` file into your `appfolder/components` directory.

### 2. Complete Configuration
In your Yii config file under the components section be sure to add 
the following details within components as a seperate settings 
array called smsSend:

```php
    'components'=>array(
        'smsSend'=>array(
            'class' => 'application.components.SendSmsComponent',
            'api_user'  => 'API username',
            'api_pass'  => 'API password',
            'api_from'  => 'FromName',
            'api_id'    => 0000000
        ),
        [...]
    ),
```

Usage
-----

### Sending a single text

```php
$sms = Yii::app()->smsSend;
$sms->postSms('070000000', "Some message", "SenderName");
```

### Getting query coverage for a phone number

```php
$sms = Yii::app()->smsSend;
if($sms->queryCoverage(070000000)){
    echo 'Can send';
}
```

### Sending batch messages

To follow. It is possible to do if you can work with the source to figure it 
yourself until I can extract an example from our exiting system.

Further Information
-------------------
* [Yii PHP Framework](http://yiiframework.com)
* [Yii Extension Site Page](http://www.yiiframework.com/extension/clickatell-smscomponent/)
* [Clickatell HTTP API documentation (pdf)](http://www.clickatell.com/downloads/http/Clickatell_HTTP.pdf)
* This component was originally adapted from a CakePHP component I made in 2008 available 
[here](https://code.google.com/p/clickatell-sms-cakephp/) (not maintained).
