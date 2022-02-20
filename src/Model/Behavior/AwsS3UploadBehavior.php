<?php
namespace Ccasanovas\S3UploadSDK\Model\Behavior;

use Cake\ORM\Behavior;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Josegonzalez\Upload\Validation\UploadValidation;
use Cake\Event\Event;
use ArrayObject;
use Exception;
use Cake\Datasource\EntityInterface;
use Cake\Validation\Validator;
use Cake\Utility\Hash;
use Cake\Core\Configure;
use Cake\Routing\Router;
use Ccasanovas\S3UploadSDK\File\Writer\AwsS3Writer;

class AwsS3UploadBehavior extends Behavior
{

	protected $_defaultConfig = [];

	public function initialize(array $config)
	{
		$local_only = Configure::read('AwsS3.local_only', false);
		$upload_config = ($local_only)
			? $this->getLocalConfig($config)
			: $this->getS3Config($config);

		$this->getTable()->addBehavior('Josegonzalez/Upload.Upload', $upload_config);
	}

	protected function getLocalConfig(array $config)
	{
		$upload_config = [];
		foreach ($this->getConfig(null, []) as $field => $config) {

			$upload_config[$field] = [
                'fields' => [
					'dir'  => Hash::get($config, 'fields.dir'),
					'size' => Hash::get($config, 'fields.size'),
					'type' => Hash::get($config, 'fields.type'),
                ],
                'path' => Hash::get($config, 'path', 'webroot{DS}files{DS}{model}{DS}{microtime}{DS}'),
                'keepFilesOnDelete' => false,
	        ];
		}

		return $upload_config;
	}

	protected function getS3Config(array $config)
	{
		$s3_key    = Configure::read('AwsS3.key', false);
		$s3_secret = Configure::read('AwsS3.secret', false);
		$s3_region = Configure::read('AwsS3.region', false);
		$s3_bucket = Configure::read('AwsS3.bucket', false);
		$s3_prefix = Configure::read('AwsS3.prefix', '');

		if(!$s3_key) 	throw new Exception(__d('Ccasanovas/S3UploadSDK', 'AwsS3.key setting missing'));
		if(!$s3_secret) throw new Exception(__d('Ccasanovas/S3UploadSDK', 'AwsS3.secret setting missing'));
		if(!$s3_region) throw new Exception(__d('Ccasanovas/S3UploadSDK', 'AwsS3.region setting missing'));
		if(!$s3_bucket) throw new Exception(__d('Ccasanovas/S3UploadSDK', 'AwsS3.bucket setting missing'));

		if(!Configure::check('AwsS3.base_url')){
			throw new Exception(__d('Ccasanovas/S3UploadSDK', 'AwsS3.base_url setting missing'));
		}

		$s3_client = S3Client::factory([
            'credentials' => [
                'key'    => $s3_key,
                'secret' => $s3_secret
            ],
            'region'  => $s3_region,
            'version' => 'latest',
        ]);

		$adapter = new AwsS3Adapter($s3_client, $s3_bucket, $s3_prefix);

		$upload_config = [];
		foreach ($this->getConfig(null, []) as $field => $config) {

			$upload_config[$field] = [
				'writer' => AwsS3Writer::class,
                'filesystem' => [
                    'adapter' => $adapter
                ],
                'fields' => [
					'dir'  => Hash::get($config, 'fields.dir'),
					'size' => Hash::get($config, 'fields.size'),
					'type' => Hash::get($config, 'fields.type'),
                ],
                'path' => Hash::get($config, 'path', '{model}{DS}{microtime}{DS}'),
                'keepFilesOnDelete' => false,
	        ];
		}

		return $upload_config;
	}

	public function buildValidator(Event $event, Validator $validator, $name)
	{
		foreach ($this->getConfig(null, []) as $field => $config) {

			$images_only = Hash::get($config, 'images_only', false);

			if($images_only){
				$validator->add($field, 'mimeType', [
	                'rule' => ['mimeType', ['image/gif', 'image/png', 'image/jpg', 'image/jpeg']],
	                'message' => __d('Ccasanovas/S3UploadSDK', 'Invalid file type. Please upload images only (gif, png, jpg).'),
	                'last' => true
	            ]);
			}

			$validator
	            ->setProvider('upload', UploadValidation::class)
	            ->add($field, 'fileUnderPhpSizeLimit', [
	                'rule' => 'isUnderPhpSizeLimit',
	                'message' => __d('Ccasanovas/S3UploadSDK', 'This file is too large'),
	                'provider' => 'upload'
	            ])
	            ->add($field, 'fileUnderFormSizeLimit', [
	                'rule' => 'isUnderFormSizeLimit',
	                'message' => __d('Ccasanovas/S3UploadSDK', 'This file is too large'),
	                'provider' => 'upload'
	            ])
	            ->add($field, 'fileCompletedUpload', [
	                'rule' => 'isCompletedUpload',
	                'message' => __d('Ccasanovas/S3UploadSDK', 'This file could not be uploaded completely'),
	                'provider' => 'upload'
	            ])
	            ->add($field, 'fileFileUpload', [
	                'rule' => 'isFileUpload',
	                'message' => __d('Ccasanovas/S3UploadSDK', 'There was no file found to upload'),
	                'provider' => 'upload'
	            ])
	            ->add($field, 'fileSuccessfulWrite', [
	                'rule' => 'isSuccessfulWrite',
	                'message' => __d('Ccasanovas/S3UploadSDK', 'This upload failed'),
	                'provider' => 'upload'
	            ]);
		}

		return $validator;
	}

	public function afterRules(Event $event, EntityInterface $entity, ArrayObject $options, $result, $operation)
	{
		//attempt to retrieve height/width of image from local file
		foreach ($this->getConfig(null, []) as $field => $config) {
			$width_field  = Hash::get($config, 'fields.image_width');
			$height_field = Hash::get($config, 'fields.image_height');

	        if(!$width_field || !$height_field) continue;

			$width  = null;
			$height = null;

	        $file = $entity->get($field);

	        if(is_array($file) && !empty($file['tmp_name'])){
	            list($width, $height) = getimagesize($file['tmp_name']);
	            $entity->set($width_field, $width);
	            $entity->set($height_field, $height);
	        }
		}
	}

	public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options)
	{
		//store full url for uploaded files
		foreach ($this->getConfig(null, []) as $field => $config) {
			$dir_field    = Hash::get($config, 'fields.dir');
			$url_field    = Hash::get($config, 'fields.url');
			$type_field   = Hash::get($config, 'fields.type');
			$size_field   = Hash::get($config, 'fields.size');
			$width_field  = Hash::get($config, 'fields.image_width');
			$height_field = Hash::get($config, 'fields.image_height');

			if($url_field && !empty($entity->get($field)) && !empty($entity->get($dir_field))){
	            $url = Configure::read('AwsS3.local_only', false)
	            	? Router::url(str_replace('webroot/', '', $entity->get($dir_field)) . $entity->get($field), true)
	            	: implode('/', array_filter([
		                Configure::read('AwsS3.base_url', null),
		                Configure::read('AwsS3.bucket', null),
		                Configure::read('AwsS3.prefix', null),
		                $entity->get($dir_field) . $entity->get($field)
		            ]));
	            $entity->set($url_field, $url);
	        }

	        if($entity->isDirty($field) && $entity->get($field) === null){
				$entity->{$dir_field}  = null;
				$entity->{$type_field} = null;
				$entity->{$size_field} = null;
				$entity->{$url_field}  = null;

				if($width_field && $height_field){
					$entity->{$width_field}  = null;
					$entity->{$height_field} = null;
				}

	        }
		}
	}

	public function deleteFile($id, $field)
	{
		$entity = $this->getTable()->get($id);

        $this->getTable()->behaviors()->get('Upload')->config([
            $field => [ 'restoreValueOnFailure' => false],
        ]);

        $entity->{$field} = null;
        $entity->setDirty($field, true);

        return $this->getTable()->save($entity);
	}


}
