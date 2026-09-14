--TEST--
soda list:rules
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$_SERVER['argv'] = ['soda', 'list:rules'];

require __DIR__ . '/../../soda';
--EXPECTF--
%A| Rule id%ASection%ASeverity%ADefault%A Label%A
%A| max_method_length%Astructural%Aerror%A100%AMethod length:%A
%A| no_trivial_delegating_classes%Astructural%Awarning%A0%ATrivial delegating classes:%A
%A| no_assignment_in_condition%Astructural%Awarning%A0%AAssignments inside conditions:%A
%A| no_else_branches%Astructural%Awarning%A0%AElse/elseif branches:%A
%A| no_complex_control_conditions%Astructural%Awarning%A0%AComplex control conditions:%A
%A| max_cyclomatic_complexity%Acomplexity%Aerror%A8%ACyclomatic complexity:%A
%A| avoid_redundant_naming%Anaming%Awarning%A80%ARedundant naming:%A
