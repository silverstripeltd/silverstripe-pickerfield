<?php

namespace TheWebmen\PickerField\Controllers;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\Filters\PartialMatchFilter;
use SilverStripe\ORM\Search\SearchContext;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchHandler;

class PickerFieldAddExistingSearchHandler extends GridFieldAddExistingSearchHandler {

	private static $allowed_actions = array(
			'index',
			'add',
			'SearchForm'
	);

	/**
	 * Builds the handler and gives page types a plain Title and Content search instead of the Pages admin search context.
	 *
	 * @param PickerField $grid - the picker field this handler searches for
	 * @param PickerFieldAddExistingSearchButton $button - the button component that opened this handler
	 */
	public function __construct($grid, $button) {
		parent::__construct($grid, $button);

		// A string check, so this stays safe when silverstripe/cms is not installed
		if(is_a($grid->getModelClass(), SiteTree::class, true)) {
			$this->context = $this->createPageSearchContext($grid->getModelClass());
		}
	}

	/**
	 * Creates the search context used for page types, with partial-match Title and Content fields.
	 *
	 * @param string $modelClass - the page class being searched
	 * @return SearchContext - a context holding only the Title and Content fields
	 */
	protected function createPageSearchContext($modelClass) {
		$fields = FieldList::create(
			TextField::create('Title', 'Page name'),
			TextField::create('Content', 'Content')
		);
		$filters = array(
			'Title'   => PartialMatchFilter::create('Title'),
			'Content' => PartialMatchFilter::create('Content')
		);

		return SearchContext::create($modelClass, $fields, $filters);
	}

	public function add($request) {
		// use native GridFieldAddExistingSearchHandler add() method when not has_one
		if(!$this->grid->isHaveOne()) { return parent::add($request); }

		if(!$id = $request->postVar('id')) {
			$this->httpError(400);
		}

		// appropriate handling of has_one relationships
		$childProperty = $this->grid->getName();
		$this->grid->childObject->$childProperty = $id;
		$this->grid->childObject->write();
	}

	public function doSearch($data, $form) {
		$list = $this->context->getQuery($data, false, null, $this->getSearchList());
		$list = $this->applySearchFilters($list);
		$list = $list->excludeByList($this->grid->getList());
		$list = new PaginatedList($list, $this->request);

		$data = $this->customise(array(
				'SearchForm' => $form,
				'Items'      => $list
		));
		return $data->index();
	}


	public function Items() {
		$list = $this->getSearchList();
		$list = $this->applySearchFilters($list);
		$list = $list->excludeByList($this->grid->getList());
		$list = new PaginatedList($list, $this->request);

		return $list;
	}

	public function getSearchList() {
		$component	= $this->grid->getConfig()->getComponentByType(PickerFieldAddExistingSearchButton::class);

		return $component->getSearchList() ?: DataList::create($this->grid->getList()->dataClass());
	}

	public function applySearchFilters($list){
		$component	= $this->grid->getConfig()->getComponentByType(PickerFieldAddExistingSearchButton::class);

		if($filters = $component->getSearchFilters())	{ $list = $list->filter($filters); }
		if($excludes = $component->getSearchExcludes())	{ $list = $list->exclude($excludes); }

		return $list;
	}
}
