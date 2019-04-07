<?php
namespace Marmot\Framework\Controller;

use Neomerx\JsonApi\Http\Request;
use Neomerx\JsonApi\Factories\Factory;
use Neomerx\JsonApi\Exceptions\JsonApiException;
use Neomerx\JsonApi\Encoder\Encoder;

use Marmot\Framework\Interfaces\IView;
use Marmot\Framework\View\ErrorView;
use Marmot\Core;

/**
 * @codeCoverageIgnore
 */
trait JsonApiTrait
{

    private $parametersChecker;

    private function getPsr7Request($nonPsr7request)
    {
        $psr7request  = new Request(function () use ($nonPsr7request) {
            return $nonPsr7request->getMethod();
        }, function ($name) use ($nonPsr7request) {
            return $nonPsr7request->getHeader($name);
        }, function () use ($nonPsr7request) {
            return $nonPsr7request->getQueryParams();
        });

        return $psr7request;
    }

    private function getParameters()
    {
        $psr7request = $this->getPsr7Request($this->getRequest());
        $factory = new Factory();
        return $factory->createQueryParametersParser()->parse($psr7request);
    }

    /**
     * 格式化传递参数
     * @return array(
     *   $filter,
     *   $sort,
     *   $curpage,
     *   $perpage
     * )
     **/
    private function formatParameters() : array
    {
        $parameters = $this->getParameters();

        if (isset($parameters->getPaginationParameters()['number'])) {
            $page = $parameters->getPaginationParameters()['number'];
        }
        if (isset($parameters->getPaginationParameters()['size'])) {
            $size = $parameters->getPaginationParameters()['size'];
        }
        $perpage = !empty($size) ? $size : 20;
        $curpage = !empty($page) ? $page : 1;

        $filter = is_array($parameters->getFilteringParameters()) ?
        $parameters->getFilteringParameters() : array();

        return array(
            $filter,
            $this->getSort(),
            $curpage,
            $perpage
        );
    }

    private function getSort()
    {
        $sort = array();
        $sortParameters = $this->getParameters()->getSortParameters();

        if (!empty($sortParameters)) {
            foreach ($sortParameters as $sortParameter) {
                $sort[$sortParameter->getField()] = $sortParameter->isAscending() ? 1 : -1;
            }
        }
        return $sort;
    }

    protected function renderView(IView $view)
    {
        $view->setEncodingParameters($this->getParameters());
        $this->render($view);
    }

    private function getIncludePaths()
    {
        return $this->getParameters()->getIncludePaths();
    }

    protected function displayError()
    {
        $this->getResponse()->setStatusCode(Core::getLastError()->getStatus());
        $this->render(new ErrorView());
    }
}
