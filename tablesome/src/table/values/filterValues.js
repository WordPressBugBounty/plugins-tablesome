let filter_translation_strings,filterValues={};filter_translation_strings="undefined"==typeof translation_strings&&"undefined"==typeof window?{contains:"contains",does_not_contain:"does_not_contain",is:"is",is_not:"is_not",starts_with:"starts_with",ends_with:"ends_with",empty:"empty",not_empty:"not_empty"}:translation_strings;const checkbox=[{value:"not_empty",label:"Is checked"},{value:"empty",label:"Is not checked"}],number=[{value:"=",label:"="},{value:"!=",label:"!="},{value:"<",label:"<"},{value:">",label:">"},{value:"<=",label:"<="},{value:">=",label:">="},{value:"empty",label:"Is empty"},{value:"not_empty",label:"Is not empty"}];filterValues.number=number;const text=[{value:"contains",label:filter_translation_strings.contains},{value:"does_not_contain",label:filter_translation_strings.does_not_contain},{value:"is",label:filter_translation_strings.is},{value:"is_not",label:filter_translation_strings.is_not},{value:"starts_with",label:filter_translation_strings.starts_with},{value:"ends_with",label:filter_translation_strings.ends_with},{value:"empty",label:filter_translation_strings.empty},{value:"not_empty",label:filter_translation_strings.not_empty}];filterValues.text=text,filterValues.textarea=text,filterValues.file=text,filterValues.url=text,filterValues.button=text,filterValues.email=text,filterValues.checkbox=checkbox;export const dateOperatorsDefinate=[{value:"=",label:"Is"},{value:"!=",label:"Is not"},{value:"empty",label:"Is empty"},{value:"not_empty",label:"Is not empty"}];export const dateOperatorsIndefinate=[{value:"<",label:"Is before"},{value:">",label:"Is after"},{value:"<=",label:"Is on or before"},{value:">=",label:"Is on or after"}];const date=[...dateOperatorsDefinate,...dateOperatorsIndefinate];filterValues.date=date;export default filterValues;export let absoluteContextuals=[{value:"today",label:"Today"},{value:"tomorrow",label:"Tomorrow"},{value:"yesterday",label:"Yesterday"},{value:"last_seven_days",label:"Last 7 days"},{value:"last_thirty_days",label:"Last 30 days"},{value:"current_month",label:"Current Month"},{value:"current_year",label:"Current Year"}];export let relativeContextuals=[{value:"month",label:"Month"},{value:"year",label:"Year"},{value:"exact_date",label:"Exact date"}];export let months=["january","february","march","april","may","june","july","august","september","october","november","december"];