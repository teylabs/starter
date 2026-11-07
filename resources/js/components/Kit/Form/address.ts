// The data and rules behind AddressFieldset, RegionSelect and AddressText, with no Vue in it (tests/js/address.test.mjs loads it directly).
// Regions: Canada's provinces and territories and the US states and DC (ISO 3166-2 codes without the country prefix), in English and French. Other countries
// take the region as free text. Country names come from Intl.DisplayNames, so the kit ships no country list.

export interface Address {
    line1?: string | null;
    line2?: string | null;
    city?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country?: string | null;
}
export type AddressPart =
    'line1' | 'line2' | 'city' | 'region' | 'postal_code' | 'country';
export interface Region {
    code: string;
    en: string;
    fr: string;
}

export const addressParts: AddressPart[] = [
    'line1',
    'line2',
    'city',
    'region',
    'postal_code',
    'country',
];
// The HTML autocomplete token of each part, so browsers and password managers fill the right field.
export const addressAutocomplete: Record<AddressPart, string> = {
    line1: 'address-line1',
    line2: 'address-line2',
    city: 'address-level2',
    region: 'address-level1',
    postal_code: 'postal-code',
    country: 'country',
};

export const regions: Record<'CA' | 'US', Region[]> = {
    CA: [
        { code: 'AB', en: 'Alberta', fr: 'Alberta' },
        { code: 'BC', en: 'British Columbia', fr: 'Colombie-Britannique' },
        { code: 'MB', en: 'Manitoba', fr: 'Manitoba' },
        { code: 'NB', en: 'New Brunswick', fr: 'Nouveau-Brunswick' },
        {
            code: 'NL',
            en: 'Newfoundland and Labrador',
            fr: 'Terre-Neuve-et-Labrador',
        },
        { code: 'NS', en: 'Nova Scotia', fr: 'Nouvelle-Écosse' },
        {
            code: 'NT',
            en: 'Northwest Territories',
            fr: 'Territoires du Nord-Ouest',
        },
        { code: 'NU', en: 'Nunavut', fr: 'Nunavut' },
        { code: 'ON', en: 'Ontario', fr: 'Ontario' },
        { code: 'PE', en: 'Prince Edward Island', fr: 'Île-du-Prince-Édouard' },
        { code: 'QC', en: 'Quebec', fr: 'Québec' },
        { code: 'SK', en: 'Saskatchewan', fr: 'Saskatchewan' },
        { code: 'YT', en: 'Yukon', fr: 'Yukon' },
    ],
    US: [
        { code: 'AL', en: 'Alabama', fr: 'Alabama' },
        { code: 'AK', en: 'Alaska', fr: 'Alaska' },
        { code: 'AZ', en: 'Arizona', fr: 'Arizona' },
        { code: 'AR', en: 'Arkansas', fr: 'Arkansas' },
        { code: 'CA', en: 'California', fr: 'Californie' },
        { code: 'CO', en: 'Colorado', fr: 'Colorado' },
        { code: 'CT', en: 'Connecticut', fr: 'Connecticut' },
        { code: 'DE', en: 'Delaware', fr: 'Delaware' },
        { code: 'DC', en: 'District of Columbia', fr: 'District de Columbia' },
        { code: 'FL', en: 'Florida', fr: 'Floride' },
        { code: 'GA', en: 'Georgia', fr: 'Géorgie' },
        { code: 'HI', en: 'Hawaii', fr: 'Hawaï' },
        { code: 'ID', en: 'Idaho', fr: 'Idaho' },
        { code: 'IL', en: 'Illinois', fr: 'Illinois' },
        { code: 'IN', en: 'Indiana', fr: 'Indiana' },
        { code: 'IA', en: 'Iowa', fr: 'Iowa' },
        { code: 'KS', en: 'Kansas', fr: 'Kansas' },
        { code: 'KY', en: 'Kentucky', fr: 'Kentucky' },
        { code: 'LA', en: 'Louisiana', fr: 'Louisiane' },
        { code: 'ME', en: 'Maine', fr: 'Maine' },
        { code: 'MD', en: 'Maryland', fr: 'Maryland' },
        { code: 'MA', en: 'Massachusetts', fr: 'Massachusetts' },
        { code: 'MI', en: 'Michigan', fr: 'Michigan' },
        { code: 'MN', en: 'Minnesota', fr: 'Minnesota' },
        { code: 'MS', en: 'Mississippi', fr: 'Mississippi' },
        { code: 'MO', en: 'Missouri', fr: 'Missouri' },
        { code: 'MT', en: 'Montana', fr: 'Montana' },
        { code: 'NE', en: 'Nebraska', fr: 'Nebraska' },
        { code: 'NV', en: 'Nevada', fr: 'Nevada' },
        { code: 'NH', en: 'New Hampshire', fr: 'New Hampshire' },
        { code: 'NJ', en: 'New Jersey', fr: 'New Jersey' },
        { code: 'NM', en: 'New Mexico', fr: 'Nouveau-Mexique' },
        { code: 'NY', en: 'New York', fr: 'New York' },
        { code: 'NC', en: 'North Carolina', fr: 'Caroline du Nord' },
        { code: 'ND', en: 'North Dakota', fr: 'Dakota du Nord' },
        { code: 'OH', en: 'Ohio', fr: 'Ohio' },
        { code: 'OK', en: 'Oklahoma', fr: 'Oklahoma' },
        { code: 'OR', en: 'Oregon', fr: 'Oregon' },
        { code: 'PA', en: 'Pennsylvania', fr: 'Pennsylvanie' },
        { code: 'RI', en: 'Rhode Island', fr: 'Rhode Island' },
        { code: 'SC', en: 'South Carolina', fr: 'Caroline du Sud' },
        { code: 'SD', en: 'South Dakota', fr: 'Dakota du Sud' },
        { code: 'TN', en: 'Tennessee', fr: 'Tennessee' },
        { code: 'TX', en: 'Texas', fr: 'Texas' },
        { code: 'UT', en: 'Utah', fr: 'Utah' },
        { code: 'VT', en: 'Vermont', fr: 'Vermont' },
        { code: 'VA', en: 'Virginia', fr: 'Virginie' },
        { code: 'WA', en: 'Washington', fr: 'Washington' },
        { code: 'WV', en: 'West Virginia', fr: 'Virginie-Occidentale' },
        { code: 'WI', en: 'Wisconsin', fr: 'Wisconsin' },
        { code: 'WY', en: 'Wyoming', fr: 'Wyoming' },
    ],
};

// The region list for a country, named in French for a French locale and in English otherwise, sorted for that language. Null: free-text region.
export function regionOptions(
    country: string | null | undefined,
    locale = 'en-CA',
): { value: string; label: string }[] | null {
    const list = country === 'CA' || country === 'US' ? regions[country] : null;
    if (!list) return null;
    const language = locale.toLowerCase().startsWith('fr') ? 'fr' : 'en';
    return list
        .map((region) => ({ value: region.code, label: region[language] }))
        .sort((a, b) => a.label.localeCompare(b.label, locale));
}

export function regionName(
    country: string | null | undefined,
    code: string | null | undefined,
    locale = 'en-CA',
): string {
    if (!code) return '';
    return (
        regionOptions(country, locale)?.find((option) => option.value === code)
            ?.label ?? code
    );
}

export function countryName(
    code: string | null | undefined,
    locale = 'en-CA',
): string {
    if (!code) return '';
    try {
        return (
            new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code
        );
    } catch {
        return code;
    }
}

// The postal code as people write it: Canada's in capitals with one space (A1A 1A1), others trimmed. Applied on blur, never while typing.
export function normalizePostal(
    country: string | null | undefined,
    value: string,
): string {
    const trimmed = value.trim();
    if (country === 'CA') {
        const packed = trimmed.toUpperCase().replace(/[\s-]+/g, '');
        return /^[A-Z]\d[A-Z]\d[A-Z]\d$/.test(packed)
            ? `${packed.slice(0, 3)} ${packed.slice(3)}`
            : trimmed.toUpperCase();
    }
    return trimmed;
}

// Whether a non-empty postal code looks right for the country. A hint only: the server validates; other countries are never second-guessed.
export function postalLooksValid(
    country: string | null | undefined,
    value: string,
): boolean {
    if (!value.trim()) return true;
    if (country === 'CA')
        return /^[A-Z]\d[A-Z] \d[A-Z]\d$/.test(normalizePostal(country, value));
    if (country === 'US') return /^\d{5}(-\d{4})?$/.test(value.trim());
    return true;
}

export interface AddressMessages {
    line1: string;
    line2: string;
    city: string;
    country: string;
    region: string;
    region_ca: string;
    region_us: string;
    postal_code: string;
    postal_code_ca: string;
    postal_code_us: string;
    postal_hint_ca: string;
    postal_hint_us: string;
    region_placeholder: string;
    country_placeholder: string;
    map: string;
}
const fallback: AddressMessages = {
    line1: 'Street address',
    line2: 'Apartment, suite or unit',
    city: 'City',
    country: 'Country',
    region: 'Region',
    region_ca: 'Province or territory',
    region_us: 'State',
    postal_code: 'Postal code',
    postal_code_ca: 'Postal code',
    postal_code_us: 'ZIP code',
    postal_hint_ca: 'Canadian postal codes look like A1A 1A1.',
    postal_hint_us: 'ZIP codes look like 12345 or 12345-6789.',
    region_placeholder: 'Choose',
    country_placeholder: 'Choose a country',
    map: 'View on a map',
};
// The strings from the kit's `ui` lang file (`address.*`, shared as `kitUi`), then the caller's `messages`. A missing key falls back to English.
export function addressMessages(
    shared: unknown,
    overrides: Partial<AddressMessages> = {},
): AddressMessages {
    const from =
        shared && typeof shared === 'object'
            ? (shared as Record<string, unknown>)
            : {};
    const out = { ...fallback };
    for (const key of Object.keys(fallback) as (keyof AddressMessages)[]) {
        const value = overrides[key] ?? from[key];
        if (typeof value === 'string' && value !== '') out[key] = value;
    }
    return out;
}

// The labels that follow the country: Province or territory and Postal code for Canada, State and ZIP code for the US, Region and Postal code otherwise.
export function addressLabels(
    country: string | null | undefined,
    messages: AddressMessages,
): { region: string; postal_code: string; postal_hint: string | null } {
    if (country === 'CA')
        return {
            region: messages.region_ca,
            postal_code: messages.postal_code_ca,
            postal_hint: messages.postal_hint_ca,
        };
    if (country === 'US')
        return {
            region: messages.region_us,
            postal_code: messages.postal_code_us,
            postal_hint: messages.postal_hint_us,
        };
    return {
        region: messages.region,
        postal_code: messages.postal_code,
        postal_hint: null,
    };
}

// The lines AddressText shows, in North American order: street, unit, "City, Region Postal", country. Empty parts drop out.
export function addressLines(
    address: Address,
    locale = 'en-CA',
    withCountry = true,
): string[] {
    const region = regionName(address.country, address.region, locale);
    const place = [
        address.city?.trim(),
        [region, address.postal_code?.trim()].filter(Boolean).join(' '),
    ]
        .filter(Boolean)
        .join(', ');
    return [
        address.line1?.trim(),
        address.line2?.trim(),
        place,
        withCountry ? countryName(address.country, locale) : '',
    ].filter((line): line is string => Boolean(line));
}
