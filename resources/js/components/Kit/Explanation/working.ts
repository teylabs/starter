import type { ExplanationNode, ExplanationSource } from './explanation.generated';
export function safeLink(url: string): string | undefined { return /^(https?:\/\/|\/(?!\/)|#)/i.test(url) ? url : undefined; }
export function sourceText(source: ExplanationSource): string {
    return [source.label, source.kind === 'manual' ? `Manual entry; ${source.author ?? 'Author unavailable'}; ${source.reason ?? ''}` : source.kind === 'config' ? `Config version ${source.version}` : 'Record', source.link && safeLink(source.link.url) ? `${source.link.label}: ${source.link.url}` : 'Source unavailable'].join(' — ');
}
export function operationText(node: ExplanationNode): string {
    if (node.kind === 'input') return 'Recorded input';
    if (!node.operation) return node.kind;
    const op = node.operation;
    return [op.name, `version ${op.version}`, `Operands in order: ${op.operandOrder.join(', ')}`, op.divisor && `Divisor: ${op.divisor}`, op.includedKeys.length && `Included: ${op.includedKeys.join(', ')}`, op.exactFraction && `Exact fraction: ${op.exactFraction.numerator}/${op.exactFraction.denominator}`, op.bounds && `Bounds: ${op.bounds.lower} to ${op.bounds.upper}; selected ${op.bounds.selected}`].filter(Boolean).join('; ');
}
export function ruleText(node: ExplanationNode): string {
    const r = node.rule;
    if (!r) return '';
    const b = r.boundaries;
    return [r.label, `version ${r.version}`, r.tier, b && `${b.lowerInclusive ? '[' : '('}${b.lower ?? 'unbounded'}, ${b.upper ?? 'unbounded'}${b.upperInclusive ? ']' : ')'} ${b.unit}`, r.rate && `Rate: ${r.rate.display}; exact ${r.rate.exact} ${r.rate.unit}`, r.orientation, r.selection].filter(Boolean).join('; ');
}
export function settlementText(node: ExplanationNode): string[] {
    const lines: string[] = [];
    if (node.unrounded !== null) lines.push(`Unrounded: ${node.unrounded}`);
    if (node.rounding) { const r = node.rounding; lines.push(`Rounding: ${r.policy}; ${r.mode}; ${r.place} places; ${r.before} → ${r.after}`); }
    if (node.allocation) { const a = node.allocation; lines.push(`Allocation: ${a.group}; ${a.policy}; exact share ${a.exactShareMinor}; base ${a.baseMinor}; remainder ${a.remainderMinor}; allocated ${a.allocatedMinor}; adjustment ${a.adjustmentMinor}; tie-break ${a.tieBreak}`); }
    return lines;
}
export function workingText(node: ExplanationNode, frozen?: { snapshotKey: string; rulesVersion: string; locale: string; frozen: boolean }): string {
    const lines: string[] = [];
    function visit(n: ExplanationNode, depth: number) {
        const indent = '  '.repeat(depth);
        lines.push(`${indent}${n.label}: ${n.value.display}`, `${indent}${operationText(n)}`);
        // Server operand order is preserved; no operation is inferred from nesting.
        for (const key of n.operation?.operandOrder ?? [...n.inputs.map(i => i.key), ...n.children.map(c => c.id)]) {
            const input = n.inputs.find(i => i.key === key);
            const child = n.children.find(c => c.id === key);
            if (input) lines.push(`${indent}  ${input.label}: ${input.value.display}; exact ${input.exact}; ${sourceText(input.source)}`);
            if (child) visit(child, depth + 1);
        }
        // Included display details must not vanish if an input isn't an operand (e.g. selection metadata).
        for (const input of n.inputs.filter(i => n.operation && !n.operation.operandOrder.includes(i.key))) lines.push(`${indent}  ${input.label}: ${input.value.display}; exact ${input.exact}; ${sourceText(input.source)}`);
        for (const child of n.children.filter(c => n.operation && !n.operation.operandOrder.includes(c.id))) visit(child, depth + 1);
        if (n.rule) lines.push(`${indent}Rule: ${ruleText(n)}`);
        for (const e of n.exclusions) lines.push(`${indent}Excluded: ${e.label}; ${e.reason}${e.source ? '; ' + sourceText(e.source) : ''}`);
        lines.push(...settlementText(n).map(s => indent + s));
        if (n.note) lines.push(indent + n.note);
    }
    visit(node, 0);
    if (frozen) lines.push(`${frozen.frozen ? 'Frozen' : 'Current'} snapshot ${frozen.snapshotKey}; schema ${node.schemaVersion}; rules ${frozen.rulesVersion}; locale ${frozen.locale}`);
    return lines.join('\n');
}

/** Display only: the server supplies every figure and the tree's operand order. */
export function ledgerRows(node: ExplanationNode): ExplanationNode[] {
    const order = node.operation?.operandOrder ?? node.children.map(child => child.id);
    const children = [...new Set([...order, ...node.children.map(child => child.id)])]
        .map(id => node.children.find(child => child.id === id)).filter((child): child is ExplanationNode => Boolean(child));
    return [...children.flatMap(ledgerRows), node];
}
export function humanNote(node: ExplanationNode): string {
    return node.note ?? [...new Set(node.inputs.filter(input => input.source.kind === 'manual' && input.source.reason).map(input => `Manual: ${input.source.reason}`))].join('; ');
}
export function humanWorkingText(node: ExplanationNode): string {
    return ledgerRows(node).map(row => [
        `${row.label}: ${row.value.display}`,
        humanNote(row),
        ...row.exclusions.map(excluded => `Excluded: ${excluded.label} — ${excluded.reason}`),
    ].filter(Boolean).join('\n')).join('\n');
}
