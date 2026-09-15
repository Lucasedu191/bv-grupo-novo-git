// node tests/tariff-history-engine-test.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const fixtures = JSON.parse(execFileSync('php', [path.join(__dirname, 'tariff-history-test.php'), '--fixtures'], {encoding: 'utf8'})).fixtures;
const sandbox = {};
sandbox.window = sandbox;
vm.createContext(sandbox);
for (const file of ['bvgn-dynamic.js', 'tariff-history-admin.js']) {
  vm.runInContext(fs.readFileSync(path.join(root, 'assets/js', file), 'utf8'), sandbox);
}
let checks = 0;
function check(actual, expected) { assert.deepEqual(JSON.parse(JSON.stringify(actual)), expected); checks++; }
function evaluate(context, date, side = 'after') {
  return sandbox.BVGN_History.evaluate(context[side], context.group, date);
}
check(evaluate(fixtures.created, '2026-09-15').values, [179, 540, 1092, 2100]);
check(evaluate(fixtures.created, '2026-09-15', 'before').values, [149, 450, 910, 1750]);
check(evaluate(fixtures.created, '2026-09-14').winner, null);
check(evaluate(fixtures.created, '2026-09-20').winner.id, 1);
check(evaluate(fixtures.created, '2026-09-21').winner, null);
check(evaluate(fixtures.price_change, '2026-09-15').values, [194, 585, 1183, 2280]);
check(evaluate(fixtures.period_change, '2026-09-15').winner, null);
check(evaluate(fixtures.period_change, '2026-09-15', 'before').winner.id, 1);
check(evaluate(fixtures.disabled, '2026-09-18').winner, null);
check(evaluate(fixtures.overlap, '2026-09-18').winner.id, 2);
check(evaluate(fixtures.overlap, '2026-09-20').winner.id, 1);
check(evaluate(fixtures.deleted, '2026-09-18').winner.id, 2);
check(evaluate(fixtures.deleted, '2026-09-20').winner, null);
check(sandbox.BVGN_History.reference(fixtures.weekly), '2026-09-20');
check(evaluate(fixtures.weekly, '2026-09-20').winner.id, 1);
check(evaluate(fixtures.weekly, '2026-09-21').winner, null);
check(evaluate(fixtures.created, '2026-02-30'), null);
const tie = JSON.parse(JSON.stringify(fixtures.overlap));
tie.after.rules.forEach(rule => { rule.priority = 10; });
tie.after.rules.sort((a, b) => a.id - b.id);
check(evaluate(tie, '2026-09-18').winner.id, 1);
const otherGroup = JSON.parse(JSON.stringify(fixtures.created));
otherGroup.group = 'B';
check(evaluate(otherGroup, '2026-09-15').winner, null);
const single = JSON.parse(JSON.stringify(fixtures.created));
single.after.rules[0].type = 'single_date';
check(evaluate(single, '2026-09-15').winner.id, 1);
check(evaluate(single, '2026-09-16').winner, null);
// Cross-check every captured event against normal public invocation, for all four durations.
const comparisons = [];
for (const [name, context] of Object.entries(fixtures)) {
  const date = sandbox.BVGN_History.reference(context);
  for (const side of ['before', 'after']) {
    for (const [index, days] of [1, 3, 7, 15].entries()) {
      // Select the first matching tier just as the public variation selector does.
      const rules = context[side].base;
      const tier = rules.find(rule => days >= rule.min && days <= rule.max)
        || rules.reduce((a, b) => !a || b.max > a.max ? b : a, null);
      sandbox.BVGN = {dynamicTariffs: context[side].rules};
      const expected = vm.runInContext(`(() => {
        const start = new Date('${date}T00:00:00');
        start.setDate(start.getDate() + ${days});
        const end = start.getFullYear() + '-' + String(start.getMonth()+1).padStart(2,'0') + '-' + String(start.getDate()).padStart(2,'0');
        const result = BVGN_Dynamic.calcularTarifaDinamica(${tier.price}, '${date}', end, ${days}, '${context.group}');
        return Number((${tier.price} * ${days} + result.extra).toFixed(2));
      })()`, sandbox);
      const preserved = sandbox.BVGN;
      check(evaluate(context, date, side).values[index], expected);
      assert.equal(sandbox.BVGN, preserved, 'Restore public configuration after history evaluation');
      if (name === 'created' && side === 'after') comparisons.push({days, public: expected, history: expected});
    }
  }
}
console.table(comparisons);
console.log(`${checks} engine comparisons/checks passed.`);
