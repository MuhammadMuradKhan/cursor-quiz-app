import { useState } from 'react';
import { TestStart } from '@/app/components/TestStart';
import { TestScreen } from '@/app/components/TestScreen';
import { TestComplete } from '@/app/components/TestComplete';

interface Question {
  id: number;
  question: string;
  options: string[];
  correctAnswer: number;
}

interface QuestionResponse {
  questionId: number;
  selectedAnswer: number | null;
  timeSpent: number;
  wasActive: boolean;
  markedInactive: boolean;
}

type TestState = 'start' | 'in-progress' | 'complete';

// Mock questions - simulating server-provided data
const MOCK_QUESTIONS: Question[] = [
  {
    id: 1,
    question: 'What is the capital of France?',
    options: ['London', 'Berlin', 'Paris', 'Madrid'],
    correctAnswer: 2,
  },
  {
    id: 2,
    question: 'Which planet is known as the Red Planet?',
    options: ['Venus', 'Mars', 'Jupiter', 'Saturn'],
    correctAnswer: 1,
  },
  {
    id: 3,
    question: 'What is the largest ocean on Earth?',
    options: ['Atlantic Ocean', 'Indian Ocean', 'Arctic Ocean', 'Pacific Ocean'],
    correctAnswer: 3,
  },
  {
    id: 4,
    question: 'Who painted the Mona Lisa?',
    options: ['Vincent van Gogh', 'Leonardo da Vinci', 'Pablo Picasso', 'Michelangelo'],
    correctAnswer: 1,
  },
  {
    id: 5,
    question: 'What is the smallest prime number?',
    options: ['0', '1', '2', '3'],
    correctAnswer: 2,
  },
];

const TOTAL_TEST_DURATION = 300; // 5 minutes in seconds

export default function App() {
  const [testState, setTestState] = useState<TestState>('start');
  const [testResponses, setTestResponses] = useState<QuestionResponse[]>([]);
  const [totalTime, setTotalTime] = useState(0);
  const [activeTime, setActiveTime] = useState(0);

  const handleStartTest = () => {
    setTestState('in-progress');
    setTestResponses([]);
    setTotalTime(0);
    setActiveTime(0);
  };

  const handleCompleteTest = (
    responses: QuestionResponse[],
    totalElapsed: number,
    activeElapsed: number
  ) => {
    setTestResponses(responses);
    setTotalTime(totalElapsed);
    setActiveTime(activeElapsed);
    setTestState('complete');
  };

  const handleRestartTest = () => {
    setTestState('start');
  };

  return (
    <div className="size-full">
      {testState === 'start' && (
        <TestStart
          questionCount={MOCK_QUESTIONS.length}
          totalDuration={TOTAL_TEST_DURATION}
          onStart={handleStartTest}
        />
      )}

      {testState === 'in-progress' && (
        <TestScreen
          questions={MOCK_QUESTIONS}
          totalTestDuration={TOTAL_TEST_DURATION}
          onComplete={handleCompleteTest}
        />
      )}

      {testState === 'complete' && (
        <TestComplete
          responses={testResponses}
          totalTime={totalTime}
          activeTime={activeTime}
          questions={MOCK_QUESTIONS}
          onRestart={handleRestartTest}
        />
      )}
    </div>
  );
}
