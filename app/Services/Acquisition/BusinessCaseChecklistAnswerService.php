<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ChecklistAnswer;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseChecklistAnswer;
use App\Services\AbstractService;
use App\Support\Acquisition\ChecklistTemplates;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif oncesi kontrol listesi cevaplari (B43, D-155).
 *
 * sync() bes temel islemin disinda, sihirbazin `checklist` bolumunu (sablon =>
 * "q1_3" => cevap) tek transaction'da cevap satirlarina isler: yeni cevap
 * acilir, degisen guncellenir, bosaltilan silinir (hareket kaydiyla). Formda
 * gelmeyen sablon ve soruya dokunulmaz; proje tipinden cikan listenin
 * cevaplari silinmez, tip yeniden secilince geri gelir.
 */
final class BusinessCaseChecklistAnswerService extends AbstractService
{
    protected string $model = BusinessCaseChecklistAnswer::class;

    protected string $orderBy = 'item_code';

    /**
     * @param  array<string, mixed>  $checklist  sablon => soru anahtari => cevap (+ belge alanlari, yok sayilir)
     */
    public function sync(BusinessCase $case, array $checklist): void
    {
        $this->transactions->run(function () use ($case, $checklist): void {
            /** @var array<string, BusinessCaseChecklistAnswer> $existing */
            $existing = BusinessCaseChecklistAnswer::query()
                ->where('business_case_id', $case->getKey())
                ->get()
                ->keyBy(static fn (BusinessCaseChecklistAnswer $row): string => $row->template_code.'|'.$row->item_code)
                ->all();

            foreach (ChecklistTemplates::codes() as $template) {
                $input = $checklist[$template] ?? null;

                if (! is_array($input)) {
                    continue;
                }

                foreach (ChecklistTemplates::items($template) as $definition) {
                    foreach ($definition['questions'] as $question) {
                        $key = ChecklistTemplates::questionKey($question);

                        if (! array_key_exists($key, $input)) {
                            continue;
                        }

                        $this->apply($case, $template, $question, ChecklistTemplates::answer($input[$key]), $existing[$template.'|'.$question] ?? null);
                    }
                }
            }
        });
    }

    /**
     * Kayitli cevaplar: sablon => soru kodu => cevap.
     *
     * @return array<string, array<string, ChecklistAnswer>>
     */
    public function answersFor(BusinessCase $case): array
    {
        $answers = [];

        foreach ($case->checklistAnswers()->get() as $row) {
            /** @var BusinessCaseChecklistAnswer $row */
            if ($row->answer !== null) {
                $answers[(string) $row->template_code][(string) $row->item_code] = $row->answer;
            }
        }

        return $answers;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $answer = $record->getAttribute('answer');

        return [
            'liste' => $record->getAttribute('template_code'),
            'madde' => $record->getAttribute('item_code'),
            'cevap' => $answer instanceof ChecklistAnswer ? $answer->value : $answer,
            'business_case_id' => $record->getAttribute('business_case_id'),
        ];
    }

    private function apply(BusinessCase $case, string $template, string $question, ?ChecklistAnswer $answer, ?BusinessCaseChecklistAnswer $current): void
    {
        if ($answer === null) {
            if ($current !== null) {
                $this->delete($current);
            }

            return;
        }

        if ($current === null) {
            $this->create([
                'business_case_id' => $case->getKey(),
                'template_code' => $template,
                'item_code' => $question,
                'answer' => $answer->value,
            ]);

            return;
        }

        if ($current->answer !== $answer) {
            $this->update($current, ['answer' => $answer->value]);
        }
    }
}
