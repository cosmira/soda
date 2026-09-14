# Practical advice and strict policies

The current standard selection and constructor defaults are unchanged. This
revision changes advice and diagnostic messages, not these four AST criteria or
metric formulas. [PracticalPolicyCompatibilityTest](../tests/unit/PracticalPolicyCompatibilityTest.php)
keeps the contrasts executable. The existing layer-dominance tests cover folder
names independently of dependency relationships.

| Policy | Decision for this revision | Remaining limitation |
| --- | --- | --- |
| `NoObjectCloning` | Keep its literal meaning: every `clone` is reported. Say so, including for a named copy method. Fresh construction satisfies this policy. | A copy method is not automatically safe: `__clone`, shared mutable fields, aliases and resource ownership matter. We do not introduce a method-name whitelist or claim a local pattern proves deep immutability. |
| `NoElseBranches` and `MaxReturnStatements` | Keep guards accepted by the first and counted by the second. Explain the tension; prefer a meaningful local helper over new forwarding classes. | A return count does not measure the difficulty of following the branches. A future standard can prefer nesting/Cognitive Complexity only after corpus review and an explicit selection change. |
| `MaxLayerDominancePercentage` | Keep its current suffix and directory convention. Label the diagnostic as naming evidence, not a dependency finding. | Renaming `Feature` to `Services` can remove the finding without changing any dependency. This cannot qualify as an architecture improvement. |
| `OnlyListArraysAllowed` | Keep pragmatic/strict modes and access criteria. Stop recommending a temporary variable as an architectural repair. | The same data can evade the nested-access detector after assignment to a local. Repeated domain interpretation or invariants justify a VO; one access alone does not. |

Projects whose design intentionally uses clone-based immutable APIs can select
checks explicitly in `Soda::configure()->with([...])`. The existing standard has
not silently become such a profile. Rule selection is not suppression: each
enabled rule still requires zero violations.

Count-based advice now starts with the owner and calling scenario. Private
methods, a coherent trait and a value object are options with different reasons
to exist. A trait can have one owner; a fluent public operation can be useful;
packing dependencies into another service does not remove their coupling. The
numeric threshold still triggers the existing finding, and a recommendation does
not prove that a particular extraction is required.

No new runtime, profile builder, overall score, automatic fix, violation budget
or hidden exception is introduced. Further standard-set decisions are recorded
in [the implementation evidence](PRAGMATIC_RULES_PROGRESS.md).
