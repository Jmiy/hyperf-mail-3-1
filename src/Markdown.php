<?php

namespace Illuminate\Mail;

use Hyperf\Stringable\Str;
use Hyperf\ViewEngine\Contract\FactoryInterface as ViewFactory;
use Hyperf\ViewEngine\HtmlString;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use League\CommonMark\Extension\Table\TableExtension;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class Markdown
{
    /**
     * The view factory implementation.
     *
     * @var \Hyperf\ViewEngine\Contract\FactoryInterface
     */
    protected $view;

    /**
     * The current theme being used when generating emails.
     *
     * @var string
     */
    protected $theme = 'default';

    /**
     * The registered component paths.
     *
     * @var array
     */
    protected $componentPaths = [];

    /**
     * Create a new Markdown renderer instance.
     *
     * @param  \Hyperf\ViewEngine\Contract\FactoryInterface  $view
     * @param  array  $options
     * @return void
     */
    public function __construct(ViewFactory $view, array $options = [])
    {
        $this->view = $view;
        $this->theme = $options['theme'] ?? 'default';
        $this->loadComponentsFrom($options['paths'] ?? []);
    }

    /**
     * Render the Markdown template into HTML.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles|null  $inliner
     * @return \Hyperf\ViewEngine\HtmlString
     */
    public function render($view, array $data = [], $inliner = null)
    {
        $this->view->flushFinderCache();

        $contents = $this->view->replaceNamespace(
            'mail', $this->htmlComponentPaths()
        )->make($view, $data)->render();

        $theme = Str::contains($this->theme, '::')
            ? $this->theme
            : 'mail::themes.'.$this->theme;

        return new HtmlString(($inliner ?: new CssToInlineStyles)->convert(
            $contents, $this->view->make($theme, $data)->render()
        ));
    }

    /**
     * Render the Markdown template into text.
     *
     * @param  string  $view
     * @param  array  $data
     * @return \Hyperf\ViewEngine\HtmlString
     */
    public function renderText($view, array $data = [])
    {
        $this->view->flushFinderCache();

        $contents = $this->view->replaceNamespace(
            'mail', $this->textComponentPaths()
        )->make($view, $data)->render();

        return new HtmlString(
            html_entity_decode(preg_replace("/[\r\n]{2,}/", "\n\n", $contents), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Parse the given Markdown text into HTML.
     *
     * @param  string  $text
     * @return \Hyperf\ViewEngine\HtmlString
     */
    public static function parse($text)
    {
        $environment = Environment::createCommonMarkEnvironment();

        $environment->addExtension(new TableExtension);

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ], $environment);

        return new HtmlString($converter->convertToHtml($text));
    }

    /**
     * Get the HTML component paths.
     *
     * @return array
     */
    public function htmlComponentPaths()
    {
        return array_map(function ($path) {
            return $path.'/html';
        }, $this->componentPaths());
    }

    /**
     * Get the text component paths.
     *
     * @return array
     */
    public function textComponentPaths()
    {
        return array_map(function ($path) {
            return $path.'/text';
        }, $this->componentPaths());
    }

    /**
     * Get the component paths.
     *
     * @return array
     */
    protected function componentPaths()
    {
        return array_unique(array_merge($this->componentPaths, [
            __DIR__.'/resources/views',
        ]));
    }

    /**
     * Register new mail component paths.
     *
     * @param  array  $paths
     * @return void
     */
    public function loadComponentsFrom(array $paths = [])
    {
        $this->componentPaths = $paths;
    }

    /**
     * Set the default theme to be used.
     *
     * @param  string  $theme
     * @return $this
     */
    public function theme($theme)
    {
        $this->theme = $theme;

        return $this;
    }
}
