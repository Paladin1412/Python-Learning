import subprocess
import time
from threading import Thread


class MonkeyTester:
    def __init__(self, times):
        self.times = times

    def monkey_test(self):
        ret = subprocess.Popen("adb shell monkey -p com.xiaodianshi.tv.yst --throttle 50 -vvv 500",shell=True,stdout=subprocess.PIPE)
        a=ret.stdout.read().decode('utf-8')
        if "Monkey finished" not in a:
            print(a)
            raise Exception("monkey test failed")
    def back_to_index(self):
        for i in range(2):
            subprocess.call("adb shell input keyevent 4",shell=True)

    def main(self):
        for i in range(self.times):
            print("This is %d testing.."%(i+1))
            self.monkey_test()
            self.back_to_index()


if __name__ == '__main__':
    mk = MonkeyTester(50)
    mk.main()
    # subprocess.check_output("adb shell cat /sdcard/ui.xml | grep '精选'", shell=True,stderr=subprocess.STDOUT)
