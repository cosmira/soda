# Защита от возврата false positive

`unit/LaravelFalsePositiveRegressionTest.php` содержит двадцать три минимальных
воспроизведение из аудита Sandbox и Laravel и четыре проверки строгой политики.
Они проходят через `Runner` с
`RuleCatalog::standard()`: тест защищает не только отдельный детектор, но и
сбор фактов, разрешение имён, связи AST и подключение правила в стандартном наборе.

Каждый случай проверяет три условия:

1. Проверяемое правило присутствует в стандартном наборе.
2. Корректный пример не вызывает это правило.
3. Парный пример настоящего нарушения вызывает это правило.

Проверки адресованы конкретным правилам: сторонние стилевые замечания в
минимальном воспроизведении не смешиваются с проверяемой регрессией.
Исходники Laravel и сеть не требуются; примеры анализируются, а не исполняются.

| Источник регрессии | Проверка стандартного конвейера | Подробные проверки границ |
|---|---|---|
| Sandbox: копия массива перед `unset` | independent array copy | `UselessVariableEscapeTest`, `UselessVariableRuleTest` |
| Prompts: присваивание в callback | callback body is outside the surrounding condition | `NoAssignmentInConditionTest` |
| Prompts: явный немедленный вызов closure | immediately invoked closure has an explicit target | `NoDynamicInvocationTest` |
| Prompts/Pail: простой запрос с instanceof или fallback | single query may be tested with instanceof; single query with null fallback | `NoComplexControlConditionsTest` |
| Prompts / Serializable Closure: альтернативные ветви не добавляют уровень | try/else and catch nesting boundaries | `CallableNestingTest` |
| Prompts: `get_defined_vars()` | parameters forwarded by variable snapshot | `NoUnusedParametersTest` |
| Prompts: числовые данные и валидация mixed | numeric data guard; validating mixed data | `NoBooleanParametersTest` |
| Scout: значения пагинации и форматирование | pagination defaults; formatting a boolean value | `NoBooleanParametersTest` |
| Scout: подготовленные настройки | prepared settings are guarded and forwarded | `TellDontAskVisitorTest` |
| Sanctum / Serializable Closure: необязательные данные | nullable model guard; optional secret | `NoBooleanParametersTest` |
| Sanctum: третий аргумент callback | third positional callback argument | `NoUnusedParametersTest` |
| Pail/Sail: второй аргумент callback | second positional callback argument | `NoUnusedParametersTest` |
| Pail/Sail: чтение значения в тернарном выражении | consumed ternary reads a value | `TellDontAskVisitorTest` |
| Pail: объект управляет своим состоянием | object owns its state decision | `TellDontAskVisitorTest` |
| Serializable Closure: пример в комментарии | inline example is documentation | `CommentedCodeDetectorTest` |
| Serializable Closure: потоковая обёртка | native stream registration | `NoUnusedParametersTest`, `MaxArgumentsContractTest` |

Контракты Eloquent дополнительно закреплены в `MaxArgumentsContractTest` и
`NoUntypedParametersTest`: точные сигнатуры и совместимый `mixed`.

Четыре дополнительные пары `Strict policy` сохраняют намеренно строгие запреты
`else`, `elseif` и числовых индексов. Корректность результата выполнения и
наличие ключа не отменяют нарушение стандарта. Уточнение политики не меняет
ожидания остальных регрессий.

Быстрый прогон двадцати семи пар:

```sh
php vendor/bin/phpunit --filter LaravelFalsePositiveRegressionTest
```

Все проверки автоматически входят в `composer test` через `tests/unit`.
Существующий `.github/workflows/ci.yml` выполняет эту команду в CI.

При новом подтверждённом false positive добавлять минимальное воспроизведение
и парный настоящий дефект, указывать источник и сохранять проверку через
стандартный конвейер. Изменение ожидаемых результатов должно сопровождаться
объяснением изменения семантики правила. Простое уменьшение количества сообщений
в отчёте не заменяет регрессионный тест.

`InheritedParameterContractsTest` дополнительно проверяет контракты между файлами,
независимость от порядка файлов, наследование через промежуточный класс, лишние
параметры реализации, private/неизвестного родителя и конструкторы.

`ExternalParameterContractsTest` проверяет установленные PSR-4-зависимости
в изолированном временном Composer-проекте: транзитивное наследование, лишние
входы, отсутствие исполнения PHP/autoload и неразрешённые декларации.

PHPDoc-потребители Serializable Closure закреплены парным примером стандартного
конвейера. `LocalInterfaceRulesTest` дополнительно проверяет `@var`, `@param`,
`@return`, template bound из Sanctum и отсутствие самоподтверждающихся циклов.

`FactoryHookContractsTest` закрепляет hooks Scout: реальный динамический вызов
в предке и в установленной зависимости, известный метод предка, private-метод,
похожие имена без вызова, другой объект, повторное присваивание и вложенный scope.
Парный пример `Scout: construction method is a dispatched factory hook` проходит
через стандартный Runner.

`LocalInterfaceRulesTest` закрепляет ограниченность локального вывода об интерфейсе
и сохранение явно объявленного внешнего контракта.

## Контрпримеры Sandbox после первоначального аудита

`CallableContractRegressionTest` проходит через стандартный Runner: отсутствие
сведений о callback, его свойстве, результате метода или коллекции не порождает
нарушение. Вычисляемый метод, класс и косвенный helper по-прежнему срабатывают.
Тест также сохраняет контракт наследуемого метода и проверяет лишние аргументы.
`ExternalParameterContractsTest` проверяет Composer-зависимости без исполнения кода.
`LaravelMiddlewareContractsTest` сохраняет два контрактных входа `terminate`
зарегистрированного middleware; лишние входы и совпадения имён проверяются.

Общий стандарт доказательств: `docs/ANALYSIS_EVIDENCE.md`.
Воспроизводимая проверка корпуса: `LaravelAuditCorpusTest` и инструкция в реестре.

## Полный аудит Sandbox

`MaxArgumentsContractTest::testForwardingConstructorRequiresTheActualParentContract`
защищает прозрачную передачу параметров реальному родителю и парные нарушения.
`SandboxAuditCorpusTest` проверяет полноту ручного реестра; при `SODA_SANDBOX_ROOT`
также сверяет все исходники, родительский контракт и каждую текущую диагностику.
Реестр: `docs/research/sandbox-audit/README.md`.

`CloningPolicyTest` защищает отсутствие безусловного запрета clone в стандартном
наборе, секционном наборе и новой конфигурации; отдельно проверяет копию Query
Builder в условии и сохранение замечания к вложенному factory-вызову. Явное
подключение старой политики защищает `PracticalPolicyCompatibilityTest`.
