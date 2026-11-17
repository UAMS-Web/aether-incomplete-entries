import { existsSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { parse } from 'yaml'

// Every job in the workflows that run on a pull request carries the RUN_REMOTE_CI gate (#16), so a
// private repository switches remote CI off with a variable instead of deleting the workflows.
// A job added without it would bill Actions minutes on a private repository, so this fails then.
const GATE = "vars.RUN_REMOTE_CI != 'false'"
// The aggregate `CI` job needs `always()` so it still reports when a needed job fails, and repeats
// the gate beside it so the switch skips it with the rest.
const AGGREGATE_GATE = `always() && ${GATE}`
const WORKFLOWS_DIR = join(import.meta.dirname, '..', '.github', 'workflows')
// The pull-request body checks come either as the one `pr-body-checks.yml` caller or as the two
// workflows it replaces; a repository carries one form or the other, so the sync that converts it
// does not leave this test pointing at files that are gone.
const BODY_CHECK_FORMS = [['pr-body-checks.yml'], ['check-attribution-footer.yml', 'check-closing-keywords.yml']]

/**
 * The workflow files this repository gates: `ci.yml` and whichever form of the body checks it carries.
 *
 * @param {(file: string) => boolean} exists Whether a workflow file is present.
 * @returns {string[]} The file names to check.
 * @throws {Error} When neither form of the body checks is complete, so a missing file cannot read as gated.
 */
export function gatedWorkflows(exists) {
    const form = BODY_CHECK_FORMS.find((files) => files.every(exists))
    if (form === undefined) {
        throw new Error('neither pr-body-checks.yml nor the two workflows it replaces are present')
    }

    return ['ci.yml', ...form]
}

/**
 * The ids of the jobs in a workflow whose `if:` is not the gate.
 *
 * @param {string} source The workflow's YAML.
 * @returns {string[]} The job ids without the gate; empty when every job has it.
 */
export function ungatedJobs(source) {
    const jobs = parse(source)?.jobs
    if (jobs === null || typeof jobs !== 'object') {
        throw new Error('workflow has no jobs mapping')
    }

    return Object.entries(jobs)
        .filter(([, job]) => job?.if !== GATE && job?.if !== AGGREGATE_GATE)
        .map(([id]) => id)
}

describe('the RUN_REMOTE_CI gate', () => {
    for (const file of gatedWorkflows((name) => existsSync(join(WORKFLOWS_DIR, name)))) {
        it(`is on every job of ${file}`, () => {
            const source = readFileSync(join(WORKFLOWS_DIR, file), 'utf8')

            expect(ungatedJobs(source)).toEqual([])
        })
    }

    it('names a job added without it', () => {
        const source = ['jobs:', '  gated:', `    if: ${GATE}`, '    runs-on: ubuntu-latest', '  added:', '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual(['added'])
    })

    it('accepts the aggregate form, always() beside the gate', () => {
        const source = ['jobs:', '  ci:', `    if: ${AGGREGATE_GATE}`, '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual([])
    })

    it('names a bare always(), which would run with the switch off', () => {
        const source = ['jobs:', '  ci:', '    if: always()', '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual(['ci'])
    })

    it('accepts a reporter job that runs always() beside the gate, as pr-body-checks.yml has it', () => {
        const source = ['jobs:', '  reporter:', `    if: ${AGGREGATE_GATE}`, '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual([])
    })

    it('names a job with the gate inside an or, which would run with the switch off', () => {
        const source = ['jobs:', '  ci:', `    if: always() || ${GATE}`, '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual(['ci'])
    })

    it('names a job whose condition is something else', () => {
        const source = ['jobs:', '  other:', "    if: github.event_name == 'push'", '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual(['other'])
    })

    it('gates the one-caller form of the body checks when it is present', () => {
        expect(gatedWorkflows((name) => name !== 'check-attribution-footer.yml' && name !== 'check-closing-keywords.yml')).toEqual(['ci.yml', 'pr-body-checks.yml'])
    })

    it('gates the two-workflow form of the body checks when the caller is absent', () => {
        expect(gatedWorkflows((name) => name !== 'pr-body-checks.yml')).toEqual(['ci.yml', 'check-attribution-footer.yml', 'check-closing-keywords.yml'])
    })

    it('refuses a repository with neither form, which would otherwise gate nothing', () => {
        expect(() => gatedWorkflows((name) => name === 'ci.yml')).toThrow('neither pr-body-checks.yml')
    })

    it('refuses a workflow with no jobs, which would otherwise read as gated', () => {
        expect(() => ungatedJobs('name: empty\n')).toThrow('no jobs mapping')
    })
})
