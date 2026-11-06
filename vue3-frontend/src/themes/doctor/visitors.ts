import type { Visitor } from '../../types/app'

const sampleVisitors: Visitor[] = [
  { id: 1, name: 'John Doe', time: '09:00', reason: 'consultation', status: 'checkedIn' },
  { id: 2, name: 'Jane Smith', time: '09:30', reason: 'followUp', status: 'waiting' },
  { id: 3, name: 'Peter Parker', time: '10:00', reason: 'newPatient', status: 'waiting' },
  { id: 4, name: 'Mary Major', time: '10:30', reason: 'exam', status: 'waiting' }
]

/** Fresh copy so each view can mutate its own queue */
export function getSampleVisitors(): Visitor[] {
  return sampleVisitors.map((v) => ({ ...v }))
}
