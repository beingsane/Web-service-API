<?php
/**
 * @package     WebService.Application
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * WebService 'content' Create method.
 *
 * @package     WebService.Application
 * @subpackage  Controller
 * @since       1.0
 */
class WebServiceControllerV1JsonBaseCreate extends WebServiceControllerV1Base
{
	/**
	 * The media files
	 */
	protected $media = null;

	/**
	 * The user that creates the content
	 */
	protected $user = null;

	/**
	 * Init parameters
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function init()
	{
		// Set the fields
		$this->readFields();

		// Init mandatory fields
		$this->getMandatoryFields();

		// Init optional fields
		$this->getOptionalFields();

		// Get media and save it
		if (isset($_FILES['screenshots']))
		{
			$this->optionalFields['media'] = $this->getMedia();
		}

		$this->loadUser();
	}

	/**
	 * Get mandatory fields from input
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function getMandatoryFields()
	{
		// Search for mandatory fields in input query
		foreach ($this->mandatoryFields as $key => $value )
		{
			// Check if mandatory field is set
			$field = $this->input->get->getString($key);
			if ( isset($field) )
			{
				$this->mandatoryFields[$key] = $field;
			}

			// Check if there is an alternative for the mandatory field
			else
			{
				foreach ($this->alternativeFields as $index => $alternative)
				{
					// Compare the key of the alternative with the mandatory field
					if (strcmp($alternative->key, $key) === 0)
					{
						// Check condition
						$condition = $this->input->get->getString($alternative->condition);
						if (isset($condition))
						{
							// Check if the alternative field is set
							$field = $this->input->get->getString($alternative->field);
							if (isset($field))
							{
								// Unset the old mandatory field
								unset($this->mandatoryFields[$key]);

								// Replace the old mandatory field with the new one
								$this->mandatoryFields[$alternative->field] = $field;

								// The order of the alternatives is important (first match is accepted)
								return;
							}
						}
					}
				}

				$this->app->errors->addError("308", array($key));
			}
		}
	}

	/**
	 * Get optional fields from input
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function getOptionalFields()
	{
		// Search for optional fields in input query
		foreach ($this->optionalFields as $key => $value )
		{
			$field = $this->input->get->getString($key);
			if ( isset($field))
			{
				$this->optionalFields[$key] = $field;
			}
			else
			{
				unset($this->optionalFields[$key]);
			}
		}
	}

	/**
	 * Save media fields to the upload folder
	 *
	 * @return  string  A string with the names of the uploaded files
	 *
	 * @since   1.0
	 */
	protected function saveMedia()
	{
		$media = $_FILES['screenshots'];

		$files = array();

		foreach ($media['name'] as $key => $value)
		{
			$ext = preg_replace('/^.*\.([^.]+)$/D', '$1', $value);
			$newName = uniqid("", true) . '.' . $ext;

			// If a file with the same name exists create a new name
			while (file_exists(JPATH_BASE . "/../www/uploads/" . $newName))
			{
				$newName = uniqid("", true) . '.' . $ext;
			}

			array_push($files, UPLOADS . $newName);

			move_uploaded_file(
					$media['tmp_name'][$key],
					JPATH_BASE . "/../www/uploads/" . $newName
					);
		}

		$mediaMap = array();

		foreach ($files as $key => $value)
		{
			$key++;
			$mediaMap["{$key}"] = $value;
		}

		return json_encode($mediaMap);
	}

	/**
	 * Get media fields from input
	 *
	 * @return  array
	 *
	 * @since   1.0
	 */
	protected function getMedia()
	{
		if (isset($_FILES['screenshots']))
		{
			try
			{
				return $this->saveMedia();
			}
			catch (Exception $e)
			{
				$this->app->errors->addError("301", $e->getMessage());
				return;
			}
		}

		return null;
	}

	/** Get the user_id from input and check if it exists
	 *
	 * @return  boolean
	 *
	 * @since   1.0
	 */
	protected function checkUserId()
	{
		$user_id = $this->input->get->getString('user_id');
		if (isset($user_id))
		{
			$user = new JUser;
			return $user->load($user_id);
		}

		return false;
	}

	/**
	 * Load user
	 *
	 * @return void
	 *
	 * @since 1.0
	 */
	protected function loadUser()
	{
		// Check if the passed user id is correct
		if ($this->checkUserId() == true)
		{
			// Load user in session
			$user_id = $this->input->get->getString('user_id');
			$session = $this->app->getSession();
			$session->set('user', new JUser($user_id));
		}
		else
		{
			// Bad user id
			$this->app->errors->addError("201", array($this->input->get->getString('user_id')));
		}
	}

	/**
	 * Controller logic
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function execute()
	{
		// Init
		$this->init();

		if ($this->app->errors->errorsExist() == true)
		{
			$this->app->setBody(json_encode($this->app->errors->getErrors()));
			$this->app->setHeader('status', $this->app->errors->getResponseCode(), true);
			return;
		}

		$data = $this->createContent();

		$this->parseData($data);
	}

	/**
	 * Create content
	 *
	 * @return  JContent
	 *
	 * @since   1.0
	 */
	protected function createContent()
	{

		$fields = implode(',', $this->mapFieldsIn(array_keys($this->mandatoryFields)));
		$fields = $fields . ',' . implode(',', $this->mapFieldsIn(array_keys($this->optionalFields)));

		// Get content state
		$modelState = $this->model->getState();

		// Set content type that we need
		$modelState->set('content.type', $this->type);

		// Set field list
		$modelState->set('content.fields', $fields);

		// Set each mandatory field
		foreach ($this->mandatoryFields as $fieldName => $fieldContent)
		{
			$modelState->set('fields.' . $this->mapIn($fieldName), $fieldContent);
		}

		// Set each optional field
		foreach ($this->optionalFields as $fieldName => $fieldContent)
		{
			$modelState->set('fields.' . $this->mapIn($fieldName), $fieldContent);
		}

		try
		{
			// Create item
			$item = $this->model->createItem();
		}
		catch (Exception $e)
		{
			throw $e;
		}

		return $item;
	}

	/**
	 * Parse the returned data from database
	 *
	 * @param   mixed  $data  Fields may be JContent, array of JContent or false
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function parseData($data)
	{
		$returnedContent = new stdClass;
		$returnedContent->id = $this->pruneFields($data, array('content_id'));
		$this->app->setBody(json_encode($returnedContent));
	}

}
