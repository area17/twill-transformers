<?php

namespace A17\Transformers\Transformers;

use Illuminate\Support\Str;
use A17\Transformers\Services\Image\Croppings;
use A17\Transformers\Transformers\Behaviours\HasMedia;
use A17\Transformers\Transformers\Media as MediaTransformer;

class Block extends Transformer
{
    use HasMedia;

    public $__browsers = [];

    private $__blocks = [];

    public function __construct($data = null)
    {
        $this->__browsers = collect();

        $this->__blocks = collect();

        parent::__construct($data);
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function getBlocks()
    {
        if ($this->__blocks->count() > 0) {
            return $this->__blocks;
        }

        if (isset($this->data->__blocks)) {
            return $this->data->__blocks;
        }

        return collect();
    }

    /**
     * @param array|\Illuminate\Support\Collection $blocks
     */
    public function setBlocks($blocks): void
    {
        $this->__blocks = $blocks;
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function getBrowsers()
    {
        if ($this->__browsers->count() > 0) {
            return $this->__browsers;
        }

        if (isset($this->data->__browsers)) {
            return $this->data->__browsers;
        }

        return collect();
    }

    /**
     * @param array|\Illuminate\Support\Collection $browsers
     */
    public function setBrowsers($browsers): void
    {
        $this->__browsers = $browsers;
    }

    protected function transformBlock()
    {
        if ($this->type === 'chapter') {
            return $this->translated($this->type, 'title');
        } elseif ($this->type === 'gallery') {
            return $this->transformGallery($this->medias);
        } elseif ($this->type === 'portrait') {
            return $this->transformPortrait();
        } elseif ($this->type === 'partner') {
            return $this->transformPartner();
        } elseif ($this->type === 'tool') {
            return $this->transformTool();
        } elseif ($this->type === 'list') {
            return $this->transformList();
        } elseif ($this->type === 'checklist') {
            return $this->transformChecklist();
        } elseif ($this->type === 'membership') {
            return $this->transformMembership();
        } elseif ($this->type === 'quick_access') {
            return $this->transformQuickAccess();
        } elseif ($this->type === 'link') {
            return $this->transformLinks();
        } elseif ($this->type === 'title') {
            return $this->transformTitle();
        } elseif ($this->type === 'activity') {
            return $this->transformActivity();
        } elseif ($this->type === 'visit_form_link') {
            return $this->transformVisitFormsLink();
        } elseif ($this->type === 'visit_page_link') {
            return $this->transformVisitPagesLink();
        } elseif ($this->type === 'button') {
            return $this->transformButton();
        } elseif ($this->type === 'image') {
            return $this->transformBlockImage();
        } elseif ($this->type === 'carousel') {
            return $this->transformCarousel();
        } elseif ($this->type === 'resource') {
            return $this->transformResource();
        } elseif ($this->type === 'accordion') {
            return $this->transformAccordion();
        } elseif (
            $this->type === 'quote_with_author' ||
            $this->type === 'quote_with_image'
        ) {
            return ['data' => $this->transformQuote()];
        } elseif ($this->type === 'program') {
            return ['data' => $this->transformProgram()];
        }

        if (filled($raw = $this->transformRawBlock())) {
            return $raw;
        }

        return [];
    }

    protected function getFrontEndDataType($type)
    {
        switch ($type) {
            case 'visit_form_link':
            case 'visit_page_link':
                return 'activity';
        }
        return $type;
    }

    private function isBlock($element)
    {
        return isset($element['block']);
    }

    private function isBlockCollection($element)
    {
        return is_traversable($element) &&
            isset($element[0]) &&
            $element[0] instanceof Block;
    }

    private function transformArtistPortrait($portrait)
    {
        if (blank($portrait)) {
            return [];
        }

        return [
            'component' => 'portrait',

            'data' => [
                'name' => $portrait->name,

                'text' => $portrait->main_info,

                'button' => [
                    'more_label' => ___('Lire plus'),
                    'less_label' => ___('Lire moins'),
                ],

                'extra_text' => $portrait->additional_info,

                'image' => $this->transformMedia($portrait),
            ],
        ];
    }

    private function transformList()
    {
        return [
            'data' => [
                'items' => $this->blocks->map(function ($block) {
                    return [
                        'label' => $this->translated($block->label),

                        'value' => $this->translated($block->value),
                    ];
                }),
            ],
        ];
    }

    private function transformPartner()
    {
        return [
            'data' => [
                'title' => $this->translated($this->title),

                'text' => $this->translated($this->text),

                'items' => $this->transformPartnerList(
                    $this->browsers['partners'],
                ),
            ],
        ];
    }

    private function transformPartnerList($partners)
    {
        return $partners->map(function ($partner) {
            return [
                'name' => $partner->name,

                'image' => $this->transformMedia($partner),
            ];
        });
    }

    protected function transformPortrait()
    {
        return [
            'data' => [
                'title' => get_translated($this->title ?? null),

                'panels' => $this->transformPortraitPanels($this->blocks),
            ],
        ];
    }

    protected function transformPortraitPanels($panels)
    {
        return collect($panels)->map(function ($panel) {
            return [
                'title' => get_translated($panel['title'] ?? null),

                'items' => $this->transformArtistPortraits(
                    $panel->browsers['artists'],
                ),
            ];
        });
    }

    public function transformArtistPortraits($portraits)
    {
        return collect($portraits)->map(function ($portrait) {
            return $this->transformArtistPortrait($portrait);
        });
    }

    private function transformProgram()
    {
        return [
            'title' => $this->translated($this->title),

            'panels' => $this->transformProgramPanels($this->blocks),
        ];
    }

    private function transformProgramPanels($panels)
    {
        return $panels->map(function ($panel) {
            return [
                'title' => $this->translated($panel->title),

                'items' => $this->transformProgramDates($panel->blocks),
            ];
        });
    }

    private function transformProgramDates($dates)
    {
        return [
            [
                'component' => 'program',

                'data' => [
                    'items' => $dates->map(function ($date) {
                        return [
                            'date' => $this->translated($date->date),

                            'sessions' => $this->transformProgramSessions(
                                $date->blocks,
                            ),
                        ];
                    }),
                ],
            ],
        ];
    }

    private function transformProgramSessions($sessions)
    {
        return $sessions->map(function ($session) {
            return [
                'hour' => $this->translated($session->hour),

                'title' => $this->translated($session->title),

                'link' => [
                    'label' => $this->translated($session->label),

                    'url' => $this->translated($session->url),
                ],

                'text' => $this->translated($session->text),
            ];
        });
    }

    private function transformQuickAccess()
    {
        // dd($this);
    }

    private function transformLinks()
    {
        return [
            'data' => $this->blocks->map(function ($block) {
                return [
                    'label' => $this->translated($block->label),

                    'url' => $this->translated($block->url),

                    'is_external' => $block->is_external,

                    'icon' => $block->icon,
                ];
            }),
        ];
    }

    private function transformTitle()
    {
        return [
            'data' =>
                [
                    'title' => $this->translated($this->title),
                    'anchor' => Str::slug($this->translated($this->anchor)),
                ] + (!empty(($icon = $this->icon)) ? ['icon' => $icon] : []),
        ];
    }

    private function transformButton()
    {
        return [
            'data' => [
                'items' => [
                    [
                        'label' => $this->translated($this->label),
                        'url' => $this->translated($this->url),
                    ],
                ],
            ],
        ];
    }

    private function transformQuote()
    {
        $this->data->type = 'quote';

        return [
            'text' => $this->translated($this->quote),

            'author' => $this->author,

            'image' => $this->transformMedia(),
        ];
    }

    protected function transformRawBlock()
    {
        return [
            'data' => $this->transformRawBlockData($this),
        ];
    }

    private function transformRawBlockData(Block $block)
    {
        if (!is_array($block->content)) {
            return null;
        }

        $data = collect($block->content)
            ->keys()
            ->mapWithKeys(function ($key) use ($block) {
                $content = array_key_exists(
                    app()->getLocale(),
                    collect($block->content[$key] ?? [])->toArray(),
                )
                    ? $block->content[$key][app()->getLocale()]
                    : $block->content[$key] ?? null;

                return [$key => $content];
            });

        if (filled($block->medias ?? null) && $block->medias->count() > 0) {
            $data['items'] = $block->medias->map(function ($image) use (
                $block
            ) {
                return [
                    'type' => 'image',

                    'data' => $block->transformMedia($image),
                ];
            });
        }

        return $data;
    }

    private function transformText()
    {
        return [
            'data' => [
                'title' => $this->translated($this->title),

                'text' => $this->translated($this->text, 'html'),
            ],
        ];
    }

    private function transformTool()
    {
        return [
            'data' => [
                'title' => $this->translated($this->title),

                'items' => $this->transformToolsList($this->browsers['tools']),
            ],
        ];
    }

    private function transformToolsList($tools)
    {
        return collect($tools)->map(function ($tool) {
            $result = [
                'type' => $tool->icon,

                'title' => $tool->title,

                'text' => $tool->text,
            ];

            if ($tool->type === 'button') {
                $result['button'] = [
                    'label' => $tool->button_label,

                    'url' => $tool->button_url,
                ];
            }

            if ($tool->type === 'link') {
                $result['link'] = [
                    'label' => $tool->link_label,

                    'url' => $tool->link_url,
                ];
            }

            return $result;
        });
    }

    protected function translated($source, $property = null)
    {
        $translated = is_array($source)
            ? get_translated($source)
            : $this->translatedInput($source);

        if (blank($property)) {
            return $translated;
        }

        return [
            'data' => [
                $property => $translated,
            ],
        ];
    }

    public function transform()
    {
        if ($this->isBlockCollection($this->data)) {
            return collect($this->data)->map(function ($item) {
                return (new self($item))->transform();
            });
        }

        $block = (new self($this->data))->transformBlock() ?? [];

        if (
            blank($block) ||
            blank($block->type ?? $this->getFrontEndDataType($this->type))
        ) {
            return [];
        }

        return [
            'type' => $block->type ?? $this->getFrontEndDataType($this->type),
        ] + $block;
    }

    public function pushBlocks($blocks)
    {
        collect($blocks)->each(function ($block) {
            $this->__blocks->push($block);
        });
    }

    private function transformChecklist()
    {
        return [
            'data' => [
                'items' => $this->blocks->map(function ($block) {
                    return [
                        'title' => $this->translated($block->title),
                        'text' => $this->translated($block->text),
                    ];
                }),
            ],
        ];
    }

    private function transformMembership()
    {
        if (!empty(($url = $this->translated($this->url_become)))) {
            $button_become_member = [
                'button_become_member' => [
                    'label' => !empty(
                        ($label = $this->translated($this->label_url_become))
                    )
                        ? $label
                        : ___('Devenez membre'),
                    'url' => $url,
                ],
            ];
        }
        if (!empty(($url = $this->translated($this->url_renew)))) {
            $button_renew = [
                'button_renew' => [
                    'label' => !empty(
                        ($label = $this->translated($this->label_url_renew))
                    )
                        ? $label
                        : ___('Renouvelez'),
                    'url' => $url,
                ],
            ];
        }

        return [
            'data' => [
                'items' => [
                    [
                        'image' => $this->transformMedia(
                            $this,
                            Croppings::SQUARE_DEFAULT_ROLE_NAME,
                            Croppings::SQUARE_DEFAULT_CROP_NAME,
                        ),
                        'title' => $this->translated($this->title),
                        'price' => $this->translated($this->price),
                        'wysiwyg' => [
                            'html' => $this->translated($this->html),
                        ],
                    ] +
                    ($button_become_member ?? []) +
                    ($button_renew ?? []),
                ],
            ],
        ];
    }

    private function transformCarousel()
    {
        $items = [];
        foreach ($this->medias as $media) {
            $items[] = [
                'type' => 'image',
                'data' => $this->getMediaArraySource(
                    $this,
                    $media,
                    Croppings::FREE_RATIO_DEFAULT_ROLE_NAME,
                    Croppings::FREE_RATIO_DEFAULT_CROP_NAME,
                ),
            ];
        }

        return [
            'data' => [
                'items' => $items,
            ],
        ];
    }

    protected function transformActivity()
    {
        if (!empty(($url = $this->translated($this->url)))) {
            $button = [
                'button' => [
                    'label' => !empty(
                        ($label = $this->translated($this->label_url))
                    )
                        ? $label
                        : ___('Découvrir'),
                    'url' => $url,
                ],
            ];
        }

        return [
            'data' => [
                'items' => [
                    [
                        'image' => $this->transformMedia(
                            $this,
                            Croppings::SQUARE_DEFAULT_ROLE_NAME,
                            Croppings::SQUARE_DEFAULT_CROP_NAME,
                        ),
                        'kicker' => $this->translated($this->kicker),
                        'title' => $this->translated($this->title),
                        'text' => $this->translated($this->text),
                        'caption' => $this->translated($this->subtitle),
                    ] +
                    ($button ?? []),
                ],
            ],
        ];
    }

    protected function transformVisitFormsLink()
    {
        $form = isset($this->browsers['forms'])
            ? $this->transformFormsList($this->browsers['forms'])->first()
            : [];

        return [
            'data' => [
                'items' => [
                    [
                        'kicker' => $this->translated($this->kicker),
                    ] + $form,
                ],
            ],
        ];
    }

    protected function transformVisitPagesLink()
    {
        $form = isset($this->browsers['visits'])
            ? $this->transformVisitsList($this->browsers['visits'])->first()
            : [];

        return [
            'data' => [
                'items' => [
                    [
                        'kicker' => $this->translated($this->kicker),
                    ] + $form,
                ],
            ],
        ];
    }

    private function transformFormsList($forms)
    {
        return $forms->map(function ($form) {
            return [
                'title' => $form->title,
                'text' => $form->short_description,
                'image' => $this->transformMedia($form),
                'caption' => '',
                'button' => [
                    'label' => ___('Réservez'),
                    'url' => $form->link,
                ],
            ];
        });
    }

    private function transformVisitsList($visits)
    {
        return $visits->map(function ($visit) {
            return [
                'title' => $visit->title,
                'text' => $visit->intro_text,
                'image' => $this->transformMedia($visit),
                'caption' => '',
                'button' => [
                    'label' => ___('Réservez'),
                    'url' => $visit->link,
                ],
            ];
        });
    }

    protected function transformResource()
    {
        if (!empty(($url = $this->translated($this->url)))) {
            $link = [
                'link' => [
                    'label' => !empty(
                        ($label = $this->translated($this->label_url))
                    )
                        ? $label
                        : ___('Découvrir'),
                    'url' => $url,
                ],
            ];
        }

        return [
            'data' => [
                'items' => [
                    [
                        'image' => $this->transformMedia(),
                        'title' => $this->translated($this->title),
                        'text' => $this->translated($this->text),
                    ] +
                    ($link ?? []),
                ],
            ],
        ];
    }

    private function transformAccordion()
    {
        return [
            'data' => [
                'items' => $this->blocks->map(function ($block) {
                    if (!empty(($videoId = $block->video_id))) {
                        $video = [
                            'video' => [
                                'type' => $block->video_format ?? 'freecaster',
                                'video_id' => $videoId,
                                'autoplay' => false,
                            ],
                        ];
                    }

                    return [
                        'title' => $this->translated($block->title),
                        'text' => $this->translated($block->text),
                    ] +
                        ($video ?? []);
                }),
            ],
        ];
    }

    private function transformBlockImage()
    {
        return [
            'variation' =>
                $this->input('ratio') != 'inline' ? 'full' : 'inline',
            'data' => $this->transformMedia(),
        ];
    }
}
