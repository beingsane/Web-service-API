<?php
/**
 * @package     WebService.Application
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * WebService GET content class
 *
 * @package     WebService.Application
 * @subpackage  Controller
 * @since       1.0
 */
class WebServiceControllerV1JsonContentGet extends JControllerBase
{
	/**
	 * @var    string  The limit of the results
	 * @since  1.0
	 */
	protected $limit = 20;

	/**
	 * @var    string  The maximum number of results per page
	 * @since  1.0
	 */
	protected $maxResults = 100;

	/**
	 * @var    string  The offset of the results
	 * @since  1.0
	 */
	protected $offset = 0;

	/**
	 * @var    array  The fields of the results
	 * @since  1.0
	 */
	protected $fields;

	/**
	 * @var    string  The content id. It may be numeric id or '*' if all content is refeered
	 * @since  1.0
	 */
	protected $id = '*';

	/**
	 * @var    string  The order of the results
	 * @since  1.0
	 */
	protected $order = null;

	/**
	 * @var    string  The minimum created date of the results
	 * @since  1.0
	 */
	protected $since = '01-01-1970';

	/**
	 * @var    string  The maximum created date of the results
	 * @since  1.0
	 */
	protected $before = 'now';

	/**
	 * @var    integer  Supress response codes. 401 for Unauthorized; 200 for OK
	 * @since  1.0
	 */
	protected $responseCode = 401;

	/**
	 * Get the content ID from the input. It may also return '*' refeering all the content
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getContentId()
	{

		// Get route from the input
		$route = $this->input->get->getString('@route');

		if (preg_match("/json$/", $route) >= 0)
		{
			$route = str_replace('.json', '', $route);
		}

		// Break route into more parts
		$routeParts = explode('/', $route);

		// Content is not refered by a number id
		if (count($routeParts) > 0 && (!is_numeric($routeParts[0]) || $routeParts[0] < 0) && !empty($routeParts[0]))
		{
			throw new InvalidArgumentException('Unknown content path.', $this->responseCode);
		}

		// All content is refered
		if ( count($routeParts) == 0 || strlen($routeParts[0]) === 0 )
		{
			return $this->id;
		}

		// Specific content id
		return $routeParts[0];
	}

	/**
	 * Get the offset from input or the default one
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getOffset()
	{
		$offset = $this->input->get->getString('offset');

		if (isset($offset))
		{
			if ( is_numeric($offset) && $offset > 0)
			{
				return $offset;
			}

			throw new InvalidArgumentException('Offset should be a positive number. By default the limit is set to ' . $this->offset, $this->responseCode);
		}
		else
		{
			return $this->offset;
		}
	}

	/**
	 * Get the limit from input or the default one
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getLimit()
	{
		$limit = $this->input->get->getString('limit');

		if (isset($limit))
		{
			$limit = min($this->maxResults, $limit);
			if (is_numeric($limit) && $limit > 0)
			{
				return $limit;
			}

			throw new InvalidArgumentException(
					'Limit should be a positive number. By default the limit is set to ' . $this->limit,
					$this->responseCode
					);
		}
		else
		{
			return $this->limit;
		}
	}

	/**
	 * Get the fields from input or the default one
	 *
	 * @return  mixed
	 *
	 * @since   1.0
	 */
	protected function getFields()
	{

		$fields = $this->input->get->getString('fields');

		if (isset($fields))
		{
			$fields = preg_split('#[\s,]+#', $fields, null, PREG_SPLIT_NO_EMPTY);

			if ($fields === false)
			{
				return null;
			}

			return $fields;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Get the order from input or the default one
	 *
	 * @return  mixed
	 *
	 * @since   1.0
	 */
	protected function getOrder()
	{

		$order = $this->input->get->getString('order');

		if (isset($order))
		{
			$order = preg_split('#[\s,]+#', $order, null, PREG_SPLIT_NO_EMPTY);

			if ($order == false)
			{
				return null;
			}

			return $order;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Get the since date limitation from input or the default one
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getSince()
	{
		$since = $this->input->get->getString('since');

		if (isset($since))
		{
			$date = new JDate($since);
			if (!empty($since) && checkdate($date->__get('month'), $date->__get('day'), $date->__get('year')))
			{
				return $date->toSql();
			}

			throw new InvalidArgumentException('Since should be a valid date. By default all the results are returned.', $this->responseCode);
		}
		else
		{
			$date = new JDate($this->since);
			return $date->toSql();
		}
	}

	/**
	 * Get the before date limitation from input or the default one
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getBefore()
	{

		$before = $this->input->get->getString('before');

		if (isset($before))
		{
			$date = new JDate($before);
			if (!empty($before) && checkdate($date->__get('month'), $date->__get('day'), $date->__get('year')))
			{
				return $date->toSql();
			}

			throw new InvalidArgumentException(
					'Before should be a valid date. By default all the results until the current date are returned.',
					$this->responseCode
					);
		}
		else
		{
			$date = new JDate($this->before);
			return $date->toSql();
		}
	}

	/**
	 * Check the input for supress_response_codes = true in order to supress the error codes.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function checkSupressResponseCodes()
	{
		$errCode = $this->input->get->getString('suppress_response_codes');

		if (isset($errCode))
		{
			$errCode = strtolower($errCode);

			if (strcmp($errCode, 'true') === 0)
			{
				$this->responseCode = 200;
				return;
			}

			if (strcmp($errCode, 'false') === 0)
			{
				$this->responseCode = 401;
				return;
			}

			throw new InvalidArgumentException('suppress_response_codes should be set to true or false', $this->responseCode);
		}

	}

	/**
	 * Init parameters
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function init()
	{

		$this->checkSupressResponseCodes();

		// Content id
		$this->id = $this->getContentId();

		// Results offset
		$this->offset = $this->getOffset();

		// Results limits
		$this->limit = $this->getLimit();

		// Returned fields
		$this->fields = $this->getFields();

		// Results order
		$this->order = $this->getOrder();

		// Since
		$this->since = $this->getSince();

		// Before
		$this->before = $this->getBefore();
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
		// Init request
		$this->init();

		// Returned data
		$data = $this->getContent($this->id);

		// Format the results properly
		$this->parseData($data);
	}

	/**
	 * Get content by id or all content
	 *
	 * @return  mixed
	 *
	 * @since   1.0
	 */
	protected function getContent()
	{
		// Content model
		include_once JPATH_BASE . '/model/model.php';

		// New content model
		$model = new WebServiceContentModelBase;

		// Get content state
		$modelState = $model->getState();

		// Set content type that we need
		$modelState->set('content.type', 'general');
		$modelState->set('content.id', $this->id);

		// Set date limitations
		$modelState->set('filter.since', $this->since);
		$modelState->set('filter.before', $this->before);

		// TODO should check if order exists in fields
		// Sort order
		if ($this->order != null)
		{
			$modelState->set('filter.order', implode('\',\'', $this->order));
		}

		// A specific content is requested
		if (strcmp($this->id, '*') !== 0)
		{
			// Get the requested data
			$item = $model->getItem();

			// No item found
			if ($item == false)
			{
				return false;
			}

			$data = $model->pruneFields(array($item), $this->fields);

			return $data;
		}
		// All content is requested
		else
		{
			// Set offset and results limit
			$modelState->set('list.offset', $this->offset);
			$modelState->set('list.limit', $this->limit);

			// Get content from Database
			$items = $model->getList();

			// No items found
			if ($items == false)
			{
				return false;
			}

			$data = $model->pruneFields($items, $this->fields);

			return $data;
		}
	}

	/**
	 * Parse the returned data from database
	 *
	 * @param   mixed  $data  A JContent object, an array of JContent or a boolean.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function parseData($data)
	{
		// There is no content for the request
		if ($data == false)
		{
			$data = "No such content";
		}

		// Output the results
		$this->app->setBody(json_encode($data));
	}
}
