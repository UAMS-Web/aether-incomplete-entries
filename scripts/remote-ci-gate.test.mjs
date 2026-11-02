import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { parse } from 'yaml'

// Every job in the workflows that run on a pull request carries the RUN_REMOTE_CI gate (#16), so a
// private repository switches remote CI off with a variable instead of deleting the workflows.
// A job added without it would bill Actions minutes on a private repository, so this fails then.
const GATE = "vars.RUN_REMOTE_CI != 'false'"
const GATED_WORKFLOWS = ['ci.yml', 'check-attribution-footer.yml', 'check-closing-keywords.yml', 'check-required-checks.yml']

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
        .filter(([, job]) => job?.if !== GATE)
        .map(([id]) => id)
}

describe('the RUN_REMOTE_CI gate', () => {
    for (const file of GATED_WORKFLOWS) {
        it(`is on every job of ${file}`, () => {
            const source = readFileSync(join(import.meta.dirname, '..', '.github', 'workflows', file), 'utf8')

            expect(ungatedJobs(source)).toEqual([])
        })
    }

    it('names a job added without it', () => {
        const source = ['jobs:', '  gated:', `    if: ${GATE}`, '    runs-on: ubuntu-latest', '  added:', '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual(['added'])
    })

    it('names a job whose condition is something else', () => {
        const source = ['jobs:', '  other:', "    if: github.event_name == 'push'", '    runs-on: ubuntu-latest', ''].join('\n')

        expect(ungatedJobs(source)).toEqual(['other'])
    })

    it('refuses a workflow with no jobs, which would otherwise read as gated', () => {
        expect(() => ungatedJobs('name: empty\n')).toThrow('no jobs mapping')
    })
})
