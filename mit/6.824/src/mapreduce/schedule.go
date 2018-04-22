package mapreduce

import (
	"fmt"
	"log"
	"sync"
)

//
// schedule() starts and waits for all tasks in the given phase (mapPhase
// or reducePhase). the mapFiles argument holds the names of the files that
// are the inputs to the map phase, one per map task. nReduce is the
// number of reduce tasks. the registerChan argument yields a stream
// of registered workers; each item is the worker's RPC address,
// suitable for passing to call(). registerChan will yield all
// existing registered workers (if any) and new ones as they register.
//
func schedule(jobName string, mapFiles []string, nReduce int, phase jobPhase, registerChan chan string) {
	var ntasks int
	var nOther int // number of inputs (for reduce) or outputs (for map)
	switch phase {
	case mapPhase:
		ntasks = len(mapFiles)
		nOther = nReduce
	case reducePhase:
		ntasks = nReduce
		nOther = len(mapFiles)
	}

	fmt.Printf("Schedule: %v %v tasks (%d I/Os)\n", ntasks, phase, nOther)

	// All ntasks tasks have to be scheduled on workers. Once all tasks
	// have completed successfully, schedule() should return.
	//
	// Your code here (Part III, Part IV).
	//

	var wg sync.WaitGroup

	if phase == mapPhase {
		log.Println("map任务：", ntasks)
		// map过程
		leftTask := ntasks
		for index, file := range mapFiles {
			srv := <-registerChan
			log.Println("map任务编号：", index)
			taskArgs := DoTaskArgs{JobName: jobName, File: file, Phase: phase, TaskNumber: index, NumOtherPhase: nOther}
			wg.Add(1)
			leftTask--
			// 使用call() 来调用worker协程工作
			go func(registerChan chan string, rpcname string, args interface{}, leftTask int) {
				call(srv, rpcname, args, nil)
				if leftTask > 1 {
					registerChan <- srv
				}
				wg.Done()
				log.Println("map任务编号：完成")
			}(registerChan, "Worker.DoTask", taskArgs, leftTask)
		}
		log.Println("进入wait")
		wg.Wait()
	} else if phase == reducePhase {
		log.Println("reduce任务：", ntasks)
		// reduce过程
		leftTask := ntasks
		for i := 0; i < nReduce; i++ {
			srv := <-registerChan
			log.Println("reduce任务编号：", i)
			taskArgs := DoTaskArgs{JobName: jobName, File: "", Phase: phase, TaskNumber: i, NumOtherPhase: nOther}
			wg.Add(1)
			go func(registerChan chan string, rpcname string, args interface{}) {
				call(srv, rpcname, args, nil)
				if leftTask > 1 {
					registerChan <- srv
				}
				wg.Done()
				log.Println("reduce任务编号完成")
			}(registerChan, "Worker.DoTask", taskArgs)
		}
		// wg.Wait()
	}
	fmt.Printf("Schedule: %v done\n", phase)
}
