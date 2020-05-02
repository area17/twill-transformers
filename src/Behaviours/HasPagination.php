<?php

namespace A17\TwillTransformers\Behaviours;

trait HasPagination
{
    public function transformPagination()
    {
        return [
            'previous_url' => $this->getPreviousUrl(),

            'next_url' => $this->getNextUrl(),

            'items' => $this->getPages(),
        ];
    }

    public function getNextUrl()
    {
        if (filled($this->paginator()) && $this->paginator()->hasMorePages()) {
            return $this->buildUrlWithPage(
                $this->paginator()->currentPage() + 1,
            );
        }

        return null;
    }

    public function getPreviousUrl()
    {
        if (
            filled($this->paginator()) &&
            $this->paginator()->currentPage() > 1
        ) {
            return $this->buildUrlWithPage(
                $this->paginator()->currentPage() - 1,
            );
        }

        return null;
    }

    public function buildUrlWithPage($page)
    {
        return $this->currentUrl(['page' => $page]);
    }

    public function paginator()
    {
        return $this->pagination['paginator'] ?? null;
    }

    public function getPages()
    {
        if (blank($this->pagination['pages'] ?? null)) {
            return [];
        }

        return $this->pagination['pages']->map(function ($page) {
            $result = [
                'label' => $page['num'],
            ];

            if ($page['num'] !== '...') {
                $result = $result + [
                    'url' => $this->buildUrlWithPage($page['num']),

                    'is_active' => $page['isCurrent'],
                ];
            }

            return $result;
        });
    }
}
