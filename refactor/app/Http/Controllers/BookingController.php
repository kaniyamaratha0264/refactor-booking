<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use Illuminate\Support\Facades\Auth; // Assuming Laravel for user authentication

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * Fetches bookings based on user type or user ID.
     *
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = Auth::user(); // Use Laravel's Auth facade

        if ($user && ($user->user_type == env('ADMIN_ROLE_ID') || $user->user_type == env('SUPERADMIN_ROLE_ID'))) {
            $response = $this->repository->getAll($request);
        } elseif ($request->has('user_id')) {
            $response = $this->repository->getUsersJobs($request->get('user_id'));
        } else {
            // Handle unauthorized access or missing parameters
            return response()->json(['error' => 'Unauthorized or invalid request'], 401);
        }

        return response($response);
    }

    /**
     * Retrieves a specific booking by ID.
     *
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        if (!$job) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        return response($job);
    }

    /**
     * Creates a new booking.
     *
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $user = Auth::user(); // Use Laravel's Auth facade
        $data = $request->all();

        $response = $this->repository->store($user, $data);

        return response($response);
    }

    /**
     * Updates a booking.
     *
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $user = Auth::user(); // Use Laravel's Auth facade
        $data = array_except($request->all(), ['_token', 'submit']); // Exclude unnecessary fields

        $response = $this->repository->updateJob($id, $data, $user);

        return response($response);
    }

    /**
     * Stores immediate job details and sends email notification.
     *
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * Fetches booking history for a specific user.
     *
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if ($request->has('user_id')) {
            $response = $this->repository->getUsersJobsHistory($request->get('user_id'), $request);
            return response($response);
        }

        return null;
    }

    /**
     * Accepts a booking for a translator.
     *
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $user = Auth::user(); // Use Laravel's Auth facade
        $data = $request->all();

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }


    /**
     * Accepts a booking for a translator with job ID.
     *
     * @param Request $request
     * @return mixed
     */
    public function acceptJobWithId(Request $request)
    {
        $jobId = $request->get('job_id');
        $user = Auth::user(); // Use Laravel's Auth facade

        if (!$jobId) {
            return response()->json(['error' => 'Missing job ID'], 400);
        }

        $response = $this->repository->acceptJobWithId($jobId, $user);

        return response($response);
    }

    /**
     * Cancels a booking.
     *
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = Auth::user(); // Use Laravel's Auth facade

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * Ends a booking.
     *
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);
    }

    /**
     * Marks a customer as not called.
     *
     * @param Request $request
     * @return mixed
     */
    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);
    }

    /**
     * Retrieves potential jobs for a translator.
     *
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user = Auth::user(); // Use Laravel's Auth facade

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    /**
     * Updates distance, time, and job-related flags/comments.
     *
     * @param Request $request
     * @return mixed
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $jobId = $data['jobid'] ?? null; // Use nullish coalescing for optional 'jobid'
        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $sessionTime = $data['session_time'] ?? '';
        $adminComment = $data['admincomment'] ?? '';

        // Validate required fields
        if (!$jobId) {
            return response()->json(['error' => 'Missing job ID'], 400);
        }

        // Update distance and time in Distance model
        Distance::where('job_id', $jobId)->update([
            'distance' => $distance,
            'time' => $time,
        ]);

        // Update job flags and comments
        $affectedRows = Job::where('id', $jobId)->update([
            'admin_comments' => $adminComment,
            'flagged' => $data['flagged'] === 'true' ? 'yes' : 'no',
            'session_time' => $sessionTime,
            'manually_handled' => $data['manually_handled'] === 'true' ? 'yes' : 'no',
            'by_admin' => $data['by_admin'] === 'true' ? 'yes' : 'no',
        ]);

        if ($affectedRows > 0) {
            return response('Record updated!');
        } else {
            return response()->json(['error' => 'Failed to update job'], 500);
        }
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }
    
    public function resendNotifications(Request $request)
    {
        $data = $request->validate([
            'jobid' => 'required|integer', // Ensure job ID is present and an integer
        ]);

        $job = $this->repository->find($data['jobid']);

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push notification sent']);
    }

    /**
     * Sends SMS to Translator
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->validate([
            'jobid' => 'required|integer', // Ensure job ID is present and an integer
        ]);

        $job = $this->repository->find($data['jobid']);

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500); // Use 500 for internal server errors
        }
    }


}