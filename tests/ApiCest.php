<?php
// phpcs:ignoreFile
//namespace tests;
namespace App\Tests;
use ApiTester;
use Codeception\Example;

/**
 * Тесты API сервиса
 */
class ApiCest
{
    /**
     * Проверка работоспособности сервиса
     * @param \ApiTester $I
     */
//    public function tryApi(ApiTester $I)
//    {
//        $I->sendGet('/');
//        $I->seeResponseCodeIs(200);
//        $I->seeResponseIsJson();
//    }

    /**
     * Негативный сценарий
     * @param \ApiTester $I
     */
    public function negative(ApiTester $I)
    {
        $this->invalidateCache($I);

        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPost('/is_spam', ['text' => '']);
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'error', 'message' => 'field text required']);
    }

    /**
     * Позитивный сценарий
     * @dataProvider positiveDataProvider
     * @param \ApiTester           $I
     * @param \Codeception\Example $example
     */
    public function positive(ApiTester $I, Example $example)
    {
        $this->invalidateCache($I);

        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');

        $I->sendPost('/is_spam',
            [
                'text' => $example['text'],
                'check_rate' => $example['check_rate']
            ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson([
            'status'          => 'ok',
            'spam'            => $example['spam'],
            'normalized_text' => $example['normalized_text'],
            'reason'          => $example['reason'],
        ]);
    }

    /**
     * Сценарий проверки лимитера
     * @param \ApiTester $I
     */
    public function checkRate(ApiTester $I)
    {
        $this->invalidateCache($I);

        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->comment('Отправляем два сообщения подряд, ожидаем 3 секунды между отправками');

        /*
         * Check rate 1
         */
        $I->sendPost('/is_spam', [
            'text'       => 'Буря мглою небо кроет, Вихри снежные крутя;',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'status' => 'ok',
            'spam'   => false,
        ]);

        sleep(3);

        /*
         * Check rate 2
         */
        $I->sendPost('/is_spam', [
            'text'       => 'То, как зверь, она завоет, То заплачет, как дитя',
            'check_rate' => 1,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'status' => 'ok',
            'spam'   => false,
        ]);

        $I->comment('Отправляем третье сообщение без ожидания');

        /*
         * Check rate 3
         */
        $I->sendPost('/is_spam', [
            'text'       => 'То по кровле обветшалой, Вдруг соломой зашумит',
            'check_rate' => 1,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(
            [
                'status' => 'ok',
                'spam'   => true,
                'reason' => 'check_rate',
            ]);
    }

    /**
     * Сценарий проверки дубликатов
     *
     * @param \ApiTester $I
     */
    public function duplicates(ApiTester $I)
    {
        /*
         * Dulicates_1: Первая отправка сообщения
         */
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->comment('Отправляем два сообщения подряд, но они не похожи достаточно');

        $this->invalidateCache($I);

        $I->sendPost('/is_spam', [
            'text'       => 'Однажды, в студеную зимнюю пору. Я из лесу вышел; был сильный мороз.',
            'check_rate' => 0,
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(
            [
                'status' => 'ok',
                'spam'   => false,
            ]);

        /*
        * Dulicates_1: Вторая отправка сообщения
        */
        $I->sendPost('/is_spam', [
            'text'       => 'Однажды, в веселую летнюю пору. Я из лесу вышел; было тепло',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(
            [
                'status' => 'ok',
                'spam'   => false,
            ]);

        /*
        * Dulicates_2: два сообщения подряд
        */
        $this->invalidateCache($I);

        $I->comment('Отправляем два сообщения подряд, они похожи достаточно');
        $I->sendPost('/is_spam', [
            'text'       => 'Однажды, в студеную зимнюю пору. Я из лесу вышел; был сильный мороз.',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(
            [
                'status' => 'ok',
                'spam'   => false,
            ]);

        $I->sendPost('/is_spam', [
            'text'       => 'Вчера, в зимнюю пору. Мы из лесу пошли! был сильный мороз',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'status' => 'ok',
            'spam'   => true,
            'reason' => 'duplicate',
        ]);
    }

    private function invalidateCache(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPost('/invalidate_cache');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(
            [
                'status' => 'ok',
            ]);
    }

    /**
     * Провайдер позитивных данных
     *
     * @return array
     */
    protected function positiveDataProvider(): array
    {
        return [
            'Обычное сообщение' => [
                'text'            => 'Удачной продажи брат!',
                'spam'            => false,
                'normalized_text' => 'брат продажи удачной',
                'reason'          => '',
                'check_rate'      => 0
            ],
            'Обычное сообщение с числами' => [
                'text'            => 'Цена 200 нормальная!',
                'spam'            => false,
                'normalized_text' => 'нормальная цена',
                'reason'          => '',
                'check_rate'      => 0
            ],
            'Фильтрация стоп слов' => [
                'text'            => 'Привет! Разве много? Хорошее состояние!',
                'spam'            => false,
                'normalized_text' => 'состояние хорошее',
                'reason'          => '',
                'check_rate'      => 0
            ],
            'Сообщение содержит эл. почту' => [
                'text'            => 'Привет! Пиши на электронную почту hacker@gaga.com',
                'spam'            => true,
                'normalized_text' => 'hacker@gaga.com пиши почту электронную',
                'reason'          => 'block_list',
                'check_rate'      => 0
            ],
            'Сообщение содержит запрещенные слова' => [
                'text'            => 'Вы уже получили свою компенсацию?',
                'spam'            => true,
                'normalized_text' => 'компенсацию получили',

                'reason'          => 'block_list',
                'check_rate'      => 0
            ],
            'Сообщение содержит разные раскладки' => [
                'text'            => 'Вы уже получили свою kомпенсацию?',
                'spam'            => true,
                'normalized_text' => 'kомпенсацию получили',
                'reason'          => 'mixed_words',
                'check_rate'      => 0
            ],
        ];
    }
}
