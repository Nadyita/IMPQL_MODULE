# Implant module for my Budabot-fork

## Purpose

The purpose of this module is a command to help you calculate implant QLs, allowing
you to spot breakpoints and optimize your attribute/treatment distribution.
It is in no way related to the implant planner.

## Installation

1. Go into your bot's directory
2. `git clone -d extras/IMPQL_MODULE https://github.com/Nadyita/IMPQL_MODULE.git`
3. Restart your bot
4. Send a `config mod IMPQL_MODULE enable all` to your bot

## Usage

Show stats for implants at a given QL:
`!impql implant_ql`

Find the highest QL of an implant you can equip with given ability and treatment:
`!impql attribute_value treatment_value`

## Examples

`!impql 150`

> QL **150** <a href="#">Implant details</a> and <a href="#">Jobe Implant details</a>.

`!impql 200 500`

> With **200** Ability and **500** Treatment, the highest possible <a href="#">Implant</a> is QL **98**.

`!impql 300 720`

> With **300** Ability and **720** Treatment, the highest possible <a href="#">Implant</a> is QL **148** and the highest possible <a href="#">Jobe Implant</a> is QL **143**.

## Notice

*Ability Clusters:*

&nbsp;&nbsp;&nbsp;&nbsp;**42** (QL **147** - QL **150**) Shiny &#10132; **306** / **720**

This means that the shiny cluster will give you **+42** in any ability from QL **147** to QL **150**.
To reach the next highest bonus (**+43** at QL **151**), you will need at least **306** in the implant's ability and **720** treatment.
