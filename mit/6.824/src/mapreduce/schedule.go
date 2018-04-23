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
		log.Printf("schedule map任务：%d,文件%v\n", ntasks, mapFiles)
		// map过程
		leftTask := ntasks
		for index, file := range mapFiles {
			log.Printf("map任务编号：%d, 文件名:%s\n", index, file)
			srv := <-registerChan
			log.Printf("srv := <-registerChan完成")
			taskArgs := DoTaskArgs{JobName: jobName, File: file, Phase: phase, TaskNumber: index, NumOtherPhase: nOther}
			wg.Add(1)
			leftTask--
			// 使用call() 来调用worker协程工作
			go func(registerChan chan string, rpcname string, args interface{}, leftTask int) {
				res := call(srv, rpcname, args, nil)

				if res {
					if leftTask > 1 {
						registerChan <- srv
					}
					// log.Println("map任务编号：完成")
				} else {
					// 重新执行
					log.Println("重新执行")
					schedule(taskArgs.JobName, []string{taskArgs.File}, taskArgs.NumOtherPhase, taskArgs.Phase, registerChan)
					log.Println("重新执行结束")
				}
				wg.Done()
			}(registerChan, "Worker.DoTask", taskArgs, leftTask)
		}
		// log.Println("进入wait")
		wg.Wait()
	} else if phase == reducePhase {
		log.Printf("schedule reduce任务：%d,任务数:%v\n", ntasks, nReduce)
		// reduce过程
		leftTask := ntasks
		for i := 0; i < nReduce; i++ {
			log.Println("reduce任务编号：", i)
			srv := <-registerChan
			log.Println("srv := <-registerChan完成")
			taskArgs := DoTaskArgs{JobName: jobName, File: "", Phase: phase, TaskNumber: i, NumOtherPhase: nOther}
			wg.Add(1)
			leftTask--
			go func(registerChan chan string, rpcname string, args interface{}, leftTask int) {
				res := call(srv, rpcname, args, nil)

				if res {
					if leftTask > 1 {
						registerChan <- srv
					}
					// log.Println("reduce任务编号完成")
				} else {
					log.Println("重新执行")
					schedule(taskArgs.JobName, []string{taskArgs.File}, leftTask, taskArgs.Phase, registerChan)
					log.Println("重新执行结束")
				}
				wg.Done()

			}(registerChan, "Worker.DoTask", taskArgs, leftTask)
		}
		wg.Wait()
	}
	fmt.Printf("Schedule: %v done\n", phase)
}
