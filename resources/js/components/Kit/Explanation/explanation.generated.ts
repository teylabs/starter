export type ExplanationAllocation = {
group: string,
policy: string,
exactShareMinor: string,
baseMinor: string,
remainderMinor: string,
allocatedMinor: string,
adjustmentMinor: string,
tieBreak: string,
};
export type ExplanationBoundaries = {
lower: string | null,
upper: string | null,
lowerInclusive: boolean,
upperInclusive: boolean,
unit: string,
};
export type ExplanationClamp = {
lower: string,
upper: string,
selected: string,
};
export type ExplanationDocument = {
schemaVersion: string,
snapshotKey: string,
rulesVersion: string,
locale: string,
frozen: boolean,
roots: ExplanationNode[],
};
export type ExplanationExclusion = {
key: string,
label: string,
reason: string,
source: ExplanationSource | null,
};
export type ExplanationFraction = {
numerator: string,
denominator: string,
};
export type ExplanationInput = {
key: string,
label: string,
value: ExplanationValue,
exact: string,
source: ExplanationSource,
};
export type ExplanationKind = 'sum' | 'difference' | 'product' | 'mean' | 'percent' | 'min' | 'max' | 'clamp' | 'lookup' | 'rounding' | 'input' | 'custom';
export type ExplanationLink = {
label: string,
url: string,
};
export type ExplanationNode = {
schemaVersion: string,
id: string,
kind: ExplanationKind,
label: string,
value: ExplanationValue,
unrounded: string | null,
rounding: ExplanationRounding | null,
rule: ExplanationRule | null,
inputs: ExplanationInput[],
children: ExplanationNode[],
exclusions: ExplanationExclusion[],
note: string | null,
operation: ExplanationOperation | null,
allocation: ExplanationAllocation | null,
};
export type ExplanationOperation = {
name: string,
version: string,
operandOrder: string[],
divisor: string | null,
includedKeys: string[],
exactFraction: ExplanationFraction | null,
bounds: ExplanationClamp | null,
};
export type ExplanationRate = {
exact: string,
display: string,
unit: string,
};
export type ExplanationRounding = {
policy: string,
mode: RoundingMode,
place: number,
before: string,
after: string,
};
export type ExplanationRule = {
key: string,
version: string,
label: string,
tier: string | null,
boundaries: ExplanationBoundaries | null,
rate: ExplanationRate | null,
orientation: string | null,
selection: string | null,
};
export type ExplanationSource = {
kind: SourceKind,
key: string,
label: string,
link: ExplanationLink | null,
reason: string | null,
author: string | null,
version: string | null,
};
export type ExplanationValue = {
display: string,
minor: string,
scale: number,
currency: string | null,
unit: string | null,
};
export type RoundingMode = 'half-up' | 'half-even' | 'toward-zero' | 'floor' | 'ceiling';
export type SourceKind = 'record' | 'manual' | 'config';
